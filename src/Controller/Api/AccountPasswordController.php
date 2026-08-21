<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Cambio de contraseña del propio cliente (con la actual). */
#[Route('/api/account/password')]
#[IsGranted('ROLE_CUSTOMER')]
final class AccountPasswordController extends AbstractController
{
    private const MIN_PASSWORD = 8;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('', name: 'api_account_password_update', methods: ['POST'])]
    public function update(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $current = (string) ($payload['currentPassword'] ?? '');
        $next = (string) ($payload['newPassword'] ?? '');

        if (!$this->hasher->isPasswordValid($user, $current)) {
            return new JsonResponse(
                ['errors' => ['currentPassword' => 'La contraseña actual no es correcta.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (strlen($next) < self::MIN_PASSWORD) {
            return new JsonResponse(
                ['errors' => ['newPassword' => sprintf('La contraseña necesita al menos %d caracteres.', self::MIN_PASSWORD)]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->hasher->isPasswordValid($user, $next)) {
            return new JsonResponse(
                ['errors' => ['newPassword' => 'La nueva contraseña debe ser distinta de la actual.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setPassword($this->hasher->hashPassword($user, $next));
        $this->em->flush();

        return new JsonResponse(['data' => ['ok' => true]]);
    }
}
