<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\AvailabilityPresenter;
use App\Entity\AvailabilitySlot;
use App\Entity\Location;
use App\Repository\AvailabilitySlotRepository;
use App\Repository\LocationRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Gestión de la disponibilidad desde el panel. */
#[Route('/api/admin/availability')]
#[IsGranted('ROLE_ADMIN')]
final class AdminAvailabilityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilitySlotRepository $slots,
        private readonly LocationRepository $locations,
        private readonly AvailabilityPresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** Rango completo, incluidas las franjas cerradas. */
    #[Route('', name: 'api_admin_availability_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $location = $this->resolveLocation($request);
        if ($location instanceof JsonResponse) {
            return $location;
        }

        $from = $this->parseDate($request->query->get('from')) ?? new \DateTimeImmutable('today');
        $to = $this->parseDate($request->query->get('to')) ?? $from->modify('+60 days');

        if ($to < $from) {
            return $this->error('invalid_range', 'La fecha final es anterior a la inicial.', Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'data' => $this->presenter->byDate($this->slots->findBetween($location, $from, $to), forAdmin: true),
            'meta' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'location' => $location->getSlug()],
        ]);
    }

    /**
     * Reemplaza las franjas de un día entero. Es la operación natural del panel:
     * abres un día, montas sus horarios y guardas.
     *
     * Las franjas que ya tienen reservas no se borran aunque desaparezcan del
     * payload — se cierran. Borrarlas dejaría reservas apuntando al vacío.
     */
    #[Route('/day/{date}', name: 'api_admin_availability_save_day', methods: ['PUT'], requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function saveDay(string $date, Request $request): JsonResponse
    {
        $day = $this->parseDate($date);
        if (null === $day) {
            return $this->error('invalid_date', 'Fecha no válida.', Response::HTTP_BAD_REQUEST);
        }

        $location = $this->resolveLocation($request);
        if ($location instanceof JsonResponse) {
            return $location;
        }

        $payload = $this->decode($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $rows = $payload['slots'] ?? null;
        if (!\is_array($rows)) {
            return $this->invalid(['slots' => 'Envía un array de franjas.']);
        }

        $existing = [];
        foreach ($this->slots->findByDate($location, $day) as $slot) {
            $existing[$slot->getStartTime()->format('H:i')] = $slot;
        }

        $seen = [];

        foreach (array_values($rows) as $index => $row) {
            if (!\is_array($row)) {
                return $this->invalid(['slots' => sprintf('La franja nº %d no es válida.', $index + 1)]);
            }

            $start = $this->parseTime($row['startTime'] ?? null);
            $end = $this->parseTime($row['endTime'] ?? null);

            if (null === $start || null === $end) {
                return $this->invalid(['slots' => sprintf('La franja nº %d necesita hora de inicio y de fin (HH:MM).', $index + 1)]);
            }

            $key = $start->format('H:i');
            if (isset($seen[$key])) {
                return $this->invalid(['slots' => sprintf('Hay dos franjas que empiezan a las %s.', $key)]);
            }
            $seen[$key] = true;

            $slot = $existing[$key] ?? null;
            if (null === $slot) {
                $slot = (new AvailabilitySlot())->setLocation($location)->setDate($day)->setStartTime($start);
                $this->em->persist($slot);
            } else {
                $slot->touch();
            }

            $slot->setEndTime($end)
                ->setCapacity((int) ($row['capacity'] ?? 0))
                ->setIsOpen((bool) ($row['isOpen'] ?? true))
                ->setNote($this->nullableString($row['note'] ?? null));

            $violations = $this->validator->validate($slot);
            if (count($violations) > 0) {
                return $this->invalid([
                    'slots' => sprintf('Franja %s: %s', $key, $violations->get(0)->getMessage()),
                ]);
            }
        }

        // Lo que ya no viene en el payload: se borra si está libre, se cierra si
        // tiene reservas.
        foreach ($existing as $key => $slot) {
            if (isset($seen[$key])) {
                continue;
            }

            if ($slot->getSeatsBooked() > 0) {
                $slot->setIsOpen(false)->touch();
                continue;
            }

            $this->em->remove($slot);
        }

        $this->em->flush();

        return new JsonResponse([
            'data' => array_map(
                fn (AvailabilitySlot $s) => $this->presenter->slot($s, forAdmin: true),
                $this->slots->findByDate($location, $day),
            ),
        ]);
    }

    /** Copia las franjas de un día a otro (o a varios). */
    #[Route('/copy', name: 'api_admin_availability_copy', methods: ['POST'])]
    public function copy(Request $request): JsonResponse
    {
        $location = $this->resolveLocation($request);
        if ($location instanceof JsonResponse) {
            return $location;
        }

        $payload = $this->decode($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $source = $this->parseDate($payload['from'] ?? null);
        $targets = $payload['to'] ?? null;

        if (null === $source) {
            return $this->invalid(['from' => 'Indica el día de origen (YYYY-MM-DD).']);
        }

        if (!\is_array($targets) || [] === $targets) {
            return $this->invalid(['to' => 'Indica al menos un día de destino.']);
        }

        $sourceSlots = $this->slots->findByDate($location, $source);
        if ([] === $sourceSlots) {
            return $this->invalid(['from' => 'Ese día no tiene franjas que copiar.']);
        }

        $created = 0;
        $skipped = 0;

        foreach ($targets as $rawTarget) {
            $target = $this->parseDate($rawTarget);
            if (null === $target || $target->format('Y-m-d') === $source->format('Y-m-d')) {
                continue;
            }

            $taken = [];
            foreach ($this->slots->findByDate($location, $target) as $slot) {
                $taken[$slot->getStartTime()->format('H:i')] = true;
            }

            foreach ($sourceSlots as $origin) {
                $key = $origin->getStartTime()->format('H:i');
                if (isset($taken[$key])) {
                    // No se pisa lo que ya hay: el destino manda.
                    ++$skipped;
                    continue;
                }

                $this->em->persist(
                    (new AvailabilitySlot())
                        ->setLocation($location)
                        ->setDate($target)
                        ->setStartTime($origin->getStartTime())
                        ->setEndTime($origin->getEndTime())
                        ->setCapacity($origin->getCapacity())
                        ->setIsOpen($origin->isOpen())
                        ->setNote($origin->getNote()),
                );
                ++$created;
            }
        }

        $this->em->flush();

        return new JsonResponse(['data' => ['created' => $created, 'skipped' => $skipped]]);
    }

    #[Route('/{id}', name: 'api_admin_availability_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $slot = $this->slots->find($id);
        if (null === $slot) {
            return $this->error('slot_not_found', 'No existe esa franja.', Response::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (\array_key_exists('capacity', $payload)) {
            $slot->setCapacity((int) $payload['capacity']);
        }

        if (\array_key_exists('isOpen', $payload)) {
            $slot->setIsOpen((bool) $payload['isOpen']);
        }

        if (\array_key_exists('note', $payload)) {
            $slot->setNote($this->nullableString($payload['note']));
        }

        if (\array_key_exists('endTime', $payload)) {
            $end = $this->parseTime($payload['endTime']);
            if (null === $end) {
                return $this->invalid(['endTime' => 'Usa el formato HH:MM.']);
            }
            $slot->setEndTime($end);
        }

        $slot->touch();

        $violations = $this->validator->validate($slot);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->invalid($errors);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->slot($slot, forAdmin: true)]);
    }

    #[Route('/{id}', name: 'api_admin_availability_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $slot = $this->slots->find($id);
        if (null === $slot) {
            return $this->error('slot_not_found', 'No existe esa franja.', Response::HTTP_NOT_FOUND);
        }

        if ($slot->getSeatsBooked() > 0) {
            return $this->error(
                'slot_has_bookings',
                'Esa franja tiene reservas. Ciérrala en vez de borrarla.',
                Response::HTTP_CONFLICT,
            );
        }

        $this->em->remove($slot);

        try {
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return $this->error(
                'slot_has_bookings',
                'Esa franja está referenciada por alguna reserva.',
                Response::HTTP_CONFLICT,
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Resuelve la zona del parámetro ?location=<slug>. Devuelve error 400/404 si falta o no existe. */
    private function resolveLocation(Request $request): Location|JsonResponse
    {
        $slug = $request->query->get('location');
        if (!\is_string($slug) || '' === trim($slug)) {
            return $this->error('location_required', 'Indica la localidad (?location=<slug>).', Response::HTTP_BAD_REQUEST);
        }

        $location = $this->locations->findOneBy(['slug' => trim($slug)]);
        if (null === $location) {
            return $this->error('location_not_found', 'No existe esa localidad.', Response::HTTP_NOT_FOUND);
        }

        return $location;
    }

    private function parseDate(mixed $raw): ?\DateTimeImmutable
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return false === $date ? null : $date;
    }

    private function parseTime(mixed $raw): ?\DateTimeImmutable
    {
        if (!\is_string($raw) || 1 !== preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $raw)) {
            return null;
        }

        $time = \DateTimeImmutable::createFromFormat('!H:i', $raw);

        return false === $time ? null : $time;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /** @return array<string, mixed>|JsonResponse */
    private function decode(Request $request): array|JsonResponse
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            return $this->error('invalid_json', 'El cuerpo de la petición no es JSON válido.', Response::HTTP_BAD_REQUEST);
        }
    }

    /** @param array<string, string> $errors */
    private function invalid(array $errors): JsonResponse
    {
        return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
