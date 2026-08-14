<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\AvailabilityPresenter;
use App\Repository\AvailabilitySlotRepository;
use App\Repository\LocationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Disponibilidad pública: lo que ve quien va a reservar. */
final class AvailabilityController extends AbstractController
{
    /** Tope de días por consulta, para que nadie pida un año entero de golpe. */
    private const MAX_RANGE_DAYS = 92;

    public function __construct(
        private readonly AvailabilitySlotRepository $slots,
        private readonly LocationRepository $locations,
        private readonly AvailabilityPresenter $presenter,
    ) {
    }

    #[Route('/api/availability', name: 'api_availability', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $locationSlug = $request->query->get('location');
        if (!\is_string($locationSlug) || '' === trim($locationSlug)) {
            return $this->error('location_required', 'Indica la localidad (?location=<slug>).');
        }

        $location = $this->locations->findOneActiveBySlug(trim($locationSlug));
        if (null === $location) {
            return $this->error('location_not_found', 'No existe esa localidad.');
        }

        $today = new \DateTimeImmutable('today');

        $from = $this->parseDate($request->query->get('from')) ?? $today;
        $to = $this->parseDate($request->query->get('to')) ?? $from->modify('+30 days');

        // Se recorta el pasado ANTES de medir el rango: pedir "desde 2020" es
        // una petición razonable de "desde siempre", y debe recortarse a hoy,
        // no rebotar por ancho excesivo.
        if ($from < $today) {
            $from = $today;
        }

        if ($to < $from) {
            return $this->error('invalid_range', 'La fecha final es anterior a la inicial.');
        }

        if ($from->diff($to)->days > self::MAX_RANGE_DAYS) {
            return $this->error('range_too_wide', sprintf('El rango no puede pasar de %d días.', self::MAX_RANGE_DAYS));
        }

        $slots = $this->slots->findBetween($location, $from, $to, onlyOpen: true);

        // Una franja sin sitio se sigue devolviendo (con seatsFree 0) para que
        // el calendario pueda mostrarla como completa en vez de ocultarla, que
        // desconcierta a quien la vio disponible hace un minuto.
        $response = new JsonResponse([
            'data' => $this->presenter->byDate($slots),
            'meta' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'location' => $location->getSlug(),
            ],
        ]);

        // Caché muy corta: el cupo cambia con cada reserva.
        $response->setPublic()->setMaxAge(30);

        return $response;
    }

    private function parseDate(mixed $raw): ?\DateTimeImmutable
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return false === $date ? null : $date;
    }

    private function error(string $code, string $message): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            Response::HTTP_BAD_REQUEST,
        );
    }
}
