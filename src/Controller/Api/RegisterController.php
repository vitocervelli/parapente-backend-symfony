<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Mail\BookingMailer;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Alta de clientes. Devuelve el token directamente para que quien está
 * reservando no tenga que volver a identificarse a mitad del proceso.
 */
final class RegisterController extends AbstractController
{
    private const MIN_PASSWORD = 8;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
        private readonly JWTTokenManagerInterface $jwt,
        private readonly RateLimiterFactoryInterface $registerLimiter,
        private readonly BookingMailer $mailer,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $limit = $this->registerLimiter->create($request->getClientIp() ?? 'anon')->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['error' => ['code' => 'too_many_attempts', 'message' => 'Demasiados intentos. Prueba en unos minutos.']],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        $errors = [];

        if ('' === $email) {
            $errors['email'] = 'Escribe tu correo.';
        }

        if (strlen($password) < self::MIN_PASSWORD) {
            $errors['password'] = sprintf('La contraseña necesita al menos %d caracteres.', self::MIN_PASSWORD);
        }

        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $this->users->findOneBy(['email' => $email])) {
            // Mensaje deliberadamente neutro: confirmar qué correos existen
            // permitiría enumerar la base de clientes.
            return new JsonResponse(
                ['errors' => ['email' => 'Ese correo ya tiene cuenta. Entra con tu contraseña.']],
                Response::HTTP_CONFLICT,
            );
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_CUSTOMER'])
            ->setFullName($this->nullableString($payload['fullName'] ?? null))
            ->setIdNumber($this->nullableString($payload['idNumber'] ?? null))
            ->setPhone($this->nullableString($payload['phone'] ?? null));

        $user->setPassword($this->hasher->hashPassword($user, $password));

        $violations = $this->validator->validate($user);
        if (count($violations) > 0) {
            $fieldErrors = [];
            foreach ($violations as $violation) {
                $fieldErrors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $fieldErrors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($user);
        $this->em->flush();

        $this->mailer->welcome($user);

        return new JsonResponse([
            'data' => [
                'token' => $this->jwt->create($user),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName(),
                    'roles' => $user->getRoles(),
                ],
            ],
        ], Response::HTTP_CREATED);
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
