<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ServicePresenter;
use App\Entity\Service;
use App\Entity\ServiceExtra;
use App\Entity\ServiceInclusion;
use App\Enum\Currency;
use App\Enum\ServiceType;
use App\Repository\ExtraRepository;
use App\Repository\InclusionItemRepository;
use App\Repository\LocationRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Escritura del catálogo. Requiere token JWT con ROLE_ADMIN. */
#[Route('/api/admin/services')]
#[IsGranted('ROLE_ADMIN')]
final class AdminServiceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServiceRepository $services,
        private readonly InclusionItemRepository $items,
        private readonly ExtraRepository $extras,
        private readonly LocationRepository $locations,
        private readonly ServicePresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_services_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $services = $this->services->findAllForAdmin();

        return new JsonResponse([
            'data' => $this->presenter->services($services),
            'meta' => ['total' => count($services)],
        ]);
    }

    /**
     * Reordena en bloque. Va antes que las rutas con {id} y no colisiona con
     * ellas porque aquellas exigen que el id sea numérico.
     */
    #[Route('/reorder', name: 'api_admin_services_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $order = $payload['order'] ?? null;
        if (!\is_array($order) || [] === $order) {
            return $this->invalid(['order' => 'Envía un array con los ids en el orden deseado.']);
        }

        $positionsById = [];
        foreach (array_values($order) as $position => $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                return $this->invalid(['order' => 'Todos los ids deben ser números positivos.']);
            }
            $positionsById[$id] = $position;
        }

        $updated = $this->services->applyOrder($positionsById);
        $this->em->flush();

        return new JsonResponse(['data' => ['updated' => $updated]]);
    }

    #[Route('/{id}', name: 'api_admin_services_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $service = $this->services->findOneWithInclusions($id);

        if (null === $service) {
            return $this->notFound();
        }

        return new JsonResponse(['data' => $this->presenter->service($service)]);
    }

    #[Route('', name: 'api_admin_services_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $service = new Service();
        $this->em->persist($service);

        return $this->save($service, $payload, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_services_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $service = $this->services->findOneWithInclusions($id);

        if (null === $service) {
            return $this->notFound();
        }

        $payload = $this->decode($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $service->touch();

        return $this->save($service, $payload, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_admin_services_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $service = $this->services->find($id);

        if (null === $service) {
            return $this->notFound();
        }

        $this->em->remove($service);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Aplica el payload sobre la entidad, valida y persiste.
     *
     * @param array<string, mixed> $payload
     */
    private function save(Service $service, array $payload, int $successStatus): JsonResponse
    {
        if (\array_key_exists('name', $payload)) {
            $service->setName(trim((string) $payload['name']));
        }

        if (\array_key_exists('slug', $payload)) {
            $service->setSlug(trim((string) $payload['slug']));
        }

        if (\array_key_exists('type', $payload)) {
            $type = ServiceType::tryFrom((string) $payload['type']);
            if (null === $type) {
                return $this->invalid(['type' => 'Debe ser "standalone" o "promotion".']);
            }
            $service->setType($type);
        }

        if (\array_key_exists('currency', $payload)) {
            $currency = Currency::tryFrom((string) $payload['currency']);
            if (null === $currency) {
                return $this->invalid(['currency' => 'Debe ser "USD" o "EUR".']);
            }
            $service->setCurrency($currency);
        }

        if (\array_key_exists('priceAmount', $payload)) {
            $amount = (string) $payload['priceAmount'];
            if (1 !== preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount)) {
                return $this->invalid(['priceAmount' => 'Usa un importe como 180 o 180.50.']);
            }
            // Se normaliza a dos decimales para que coincida con lo que devuelve DECIMAL.
            $service->setPriceAmount(number_format((float) $amount, 2, '.', ''));
        }

        foreach (['tagline', 'description', 'priceNote', 'badge'] as $field) {
            if (\array_key_exists($field, $payload)) {
                $value = $payload[$field];
                $value = (null === $value || '' === $value) ? null : (string) $value;
                $service->{'set' . ucfirst($field)}($value);
            }
        }

        if (\array_key_exists('image', $payload)) {
            $service->setCoverImage($this->nullableString($payload['image']));
        }

        if (\array_key_exists('flyer', $payload)) {
            $service->setFlyerImage($this->nullableString($payload['flyer']));
        }

        if (\array_key_exists('people', $payload)) {
            $service->setPeople((int) $payload['people']);
        }

        if (\array_key_exists('durationMinutes', $payload)) {
            $raw = $payload['durationMinutes'];
            $service->setDurationMinutes((null === $raw || '' === $raw) ? null : (int) $raw);
        }

        if (\array_key_exists('seatsPerBooking', $payload)) {
            $raw = $payload['seatsPerBooking'];
            $service->setSeatsPerBooking((null === $raw || '' === $raw) ? null : (int) $raw);
        }

        if (\array_key_exists('position', $payload)) {
            $service->setPosition((int) $payload['position']);
        }

        if (\array_key_exists('isActive', $payload)) {
            $service->setIsActive((bool) $payload['isActive']);
        }

        if (\array_key_exists('showOnHome', $payload)) {
            $service->setShowOnHome((bool) $payload['showOnHome']);
        }

        if (\array_key_exists('inclusions', $payload)) {
            $error = $this->applyInclusions($service, (array) $payload['inclusions']);
            if (null !== $error) {
                return $error;
            }
        }

        if (\array_key_exists('extras', $payload)) {
            $error = $this->applyExtras($service, (array) $payload['extras']);
            if (null !== $error) {
                return $error;
            }
        }

        if (\array_key_exists('locationIds', $payload)) {
            $error = $this->applyLocations($service, (array) $payload['locationIds']);
            if (null !== $error) {
                return $error;
            }
        }

        $violations = $this->validator->validate($service);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->invalid($errors);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->service($service)], $successStatus);
    }

    /**
     * Reemplaza el conjunto completo de inclusiones. `orphanRemoval` borra las
     * filas que ya no vienen en el payload.
     *
     * @param array<int, mixed> $rows
     */
    private function applyInclusions(Service $service, array $rows): ?JsonResponse
    {
        $service->clearInclusions();

        foreach (array_values($rows) as $index => $row) {
            if (!\is_array($row)) {
                return $this->invalid(['inclusions' => 'Cada elemento debe ser un objeto.']);
            }

            $itemId = isset($row['itemId']) ? (int) $row['itemId'] : 0;
            $item = $itemId > 0 ? $this->items->find($itemId) : null;

            if (null === $item) {
                return $this->invalid(['inclusions' => sprintf('El elemento nº %d no existe en el catálogo.', $index + 1)]);
            }

            $inclusion = (new ServiceInclusion())
                ->setItem($item)
                ->setLabelOverride($this->nullableString($row['labelOverride'] ?? null))
                ->setNote($this->nullableString($row['note'] ?? null))
                ->setPosition(isset($row['position']) ? (int) $row['position'] : $index);

            $service->addInclusion($inclusion);
        }

        return null;
    }

    /**
     * Reemplaza el conjunto completo de extras que ofrece el servicio.
     * `orphanRemoval` borra las asignaciones que ya no vienen en el payload.
     *
     * @param array<int, mixed> $rows
     */
    private function applyExtras(Service $service, array $rows): ?JsonResponse
    {
        $service->clearExtras();

        foreach (array_values($rows) as $index => $row) {
            // Se acepta tanto un id suelto como un objeto {extraId, position}.
            if (\is_array($row)) {
                $extraId = isset($row['extraId']) ? (int) $row['extraId'] : 0;
                $position = isset($row['position']) ? (int) $row['position'] : $index;
            } else {
                $extraId = (int) $row;
                $position = $index;
            }

            $extra = $extraId > 0 ? $this->extras->find($extraId) : null;

            if (null === $extra) {
                return $this->invalid(['extras' => sprintf('El extra nº %d no existe en el catálogo.', $index + 1)]);
            }

            $service->addExtra(
                (new ServiceExtra())
                    ->setExtra($extra)
                    ->setPosition($position),
            );
        }

        return null;
    }

    /**
     * Reemplaza el conjunto de localidades del servicio. Exige al menos una:
     * un servicio sin zona no aparecería en ninguna parte.
     *
     * @param array<int, mixed> $ids
     */
    private function applyLocations(Service $service, array $ids): ?JsonResponse
    {
        $unique = array_values(array_unique(array_map('intval', $ids)));
        if ([] === $unique) {
            return $this->invalid(['locationIds' => 'Elige al menos una localidad.']);
        }

        $service->clearLocations();

        foreach ($unique as $index => $locationId) {
            $location = $locationId > 0 ? $this->locations->find($locationId) : null;
            if (null === $location) {
                return $this->invalid(['locationIds' => sprintf('La localidad nº %d no existe.', $index + 1)]);
            }

            $service->addLocation($location);
        }

        return null;
    }

    /** @return array<string, mixed>|JsonResponse */
    private function decode(Request $request): array|JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return $payload;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /** @param array<string, string> $errors */
    private function invalid(array $errors): JsonResponse
    {
        return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'service_not_found', 'message' => 'No existe ese servicio.']],
            Response::HTTP_NOT_FOUND,
        );
    }
}
