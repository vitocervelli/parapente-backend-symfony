<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BookingPresenter;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Listado y detalle de usuarios registrados para el panel. */
#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly BookingRepository $bookings,
        private readonly BookingPresenter $presenter,
    ) {
    }

    #[Route('', name: 'api_admin_users_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $counts = $this->bookings->countByCustomer();
        $users = $this->users->findAllOrderedByNewest();

        $data = array_map(
            fn (User $u): array => $this->present($u, $counts[$u->getId()] ?? 0),
            $users,
        );

        return new JsonResponse([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/{id}', name: 'api_admin_users_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->users->find($id);
        if (null === $user) {
            return new JsonResponse(
                ['error' => ['code' => 'user_not_found', 'message' => 'No encontramos ese usuario.']],
                Response::HTTP_NOT_FOUND,
            );
        }

        $bookings = $this->bookings->findAllForCustomerAdmin($user);

        return new JsonResponse(['data' => [
            'user' => $this->present($user, count($bookings)),
            'bookings' => $this->presenter->bookings($bookings, forAdmin: true),
        ]]);
    }

    /** @return array<string,mixed> */
    private function present(User $user, int $bookingsCount): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'idNumber' => $user->getIdNumber(),
            'phone' => $user->getPhone(),
            'isAdmin' => $user->isAdmin(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
            'bookingsCount' => $bookingsCount,
        ];
    }
}
