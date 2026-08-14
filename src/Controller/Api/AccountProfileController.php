<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Datos personales del cliente. */
#[Route('/api/account/profile')]
#[IsGranted('ROLE_CUSTOMER')]
final class AccountProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_account_profile_update', methods: ['PATCH'])]
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

        // El correo es el identificador de la sesión: cambiarlo invalidaría el
        // token en curso, así que no se toca desde aquí.
        foreach (['fullName', 'idNumber', 'phone'] as $field) {
            if (\array_key_exists($field, $payload)) {
                $value = $payload[$field];
                $value = (null === $value || '' === trim((string) $value)) ? null : trim((string) $value);
                $user->{'set' . ucfirst($field)}($value);
            }
        }

        $violations = $this->validator->validate($user);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse(['data' => [
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'idNumber' => $user->getIdNumber(),
            'phone' => $user->getPhone(),
        ]]);
    }
}
