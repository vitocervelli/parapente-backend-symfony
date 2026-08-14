<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Quién soy. Lo usan tanto el panel como el área de cliente para comprobar que
 * el token sigue vivo, así que no puede exigir ROLE_ADMIN.
 */
final class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(['data' => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'idNumber' => $user->getIdNumber(),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'isAdmin' => $user->isAdmin(),
            'displayName' => $user->getDisplayName(),
        ]]);
    }
}
