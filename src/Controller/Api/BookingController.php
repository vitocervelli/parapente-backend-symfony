<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BookingPresenter;
use App\Booking\BookingCreator;
use App\Booking\BookingException;
use App\Entity\User;
use App\Mail\BookingMailer;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Crear reservas y consultar las propias. */
#[IsGranted('ROLE_CUSTOMER')]
final class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingCreator $creator,
        private readonly BookingRepository $bookings,
        private readonly BookingPresenter $presenter,
        private readonly BookingMailer $mailer,
    ) {
    }

    #[Route('/api/bookings', name: 'api_bookings_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $customer, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $lines = $payload['lines'] ?? null;
        if (!\is_array($lines)) {
            return $this->fail('invalid_booking', 'Envía las líneas de la reserva.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $booking = $this->creator->create(
                $customer,
                $lines,
                $this->nullableString($payload['contactPhone'] ?? null),
                $this->nullableString($payload['note'] ?? null),
            );
        } catch (BookingException $e) {
            return new JsonResponse([
                'error' => array_filter([
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'context' => $e->context ?: null,
                ]),
            ], $e->statusCode);
        }

        $this->mailer->bookingCreated($booking);

        return new JsonResponse(
            ['data' => $this->presenter->booking($booking)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/account/bookings', name: 'api_account_bookings_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $customer): JsonResponse
    {
        $bookings = $this->bookings->findForCustomer($customer);

        return new JsonResponse([
            'data' => $this->presenter->bookings($bookings),
            'meta' => ['total' => count($bookings)],
        ]);
    }

    #[Route('/api/account/bookings/{reference}', name: 'api_account_bookings_show', methods: ['GET'], requirements: ['reference' => '[A-Z0-9-]+'])]
    public function show(#[CurrentUser] User $customer, string $reference): JsonResponse
    {
        // Se filtra por cliente en la propia consulta: cambiar la referencia en
        // la URL devuelve 404, no la reserva de otro.
        $booking = $this->bookings->findOneForCustomer($reference, $customer);

        if (null === $booking) {
            return $this->fail('booking_not_found', 'No encontramos esa reserva.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $this->presenter->booking($booking)]);
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
