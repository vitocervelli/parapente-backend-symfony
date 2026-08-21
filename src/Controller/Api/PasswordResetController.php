<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Mail\BookingMailer;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recuperación de contraseña sin sesión: se pide por correo un enlace con un
 * token de un solo uso (guardado como hash), y con ese token se fija una nueva.
 */
final class PasswordResetController extends AbstractController
{
    private const MIN_PASSWORD = 8;
    private const TOKEN_TTL = '+1 hour';

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly BookingMailer $mailer,
        private readonly RateLimiterFactoryInterface $passwordForgotLimiter,
    ) {
    }

    #[Route('/api/password/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgot(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->neutral();
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));

        // Se limita por correo + IP: frena tanto el spam a un buzón como la
        // enumeración de cuentas probando muchos correos.
        $limiter = $this->passwordForgotLimiter->create($email . '|' . ($request->getClientIp() ?? 'anon'));
        if (!$limiter->consume()->isAccepted()) {
            return new JsonResponse(
                ['error' => ['code' => 'too_many_attempts', 'message' => 'Demasiados intentos. Prueba en unos minutos.']],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        if ('' !== $email) {
            $user = $this->users->findOneBy(['email' => $email]);
            if (null !== $user) {
                $rawToken = bin2hex(random_bytes(32));
                $user->startPasswordReset($rawToken, new \DateTimeImmutable(self::TOKEN_TTL));
                $this->em->flush();

                $this->mailer->passwordReset($user, $rawToken);
            }
        }

        // Respuesta SIEMPRE neutra: no se revela si el correo existe o no.
        return $this->neutral();
    }

    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $token = trim((string) ($payload['token'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if (strlen($password) < self::MIN_PASSWORD) {
            return new JsonResponse(
                ['errors' => ['password' => sprintf('La contraseña necesita al menos %d caracteres.', self::MIN_PASSWORD)]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = '' === $token
            ? null
            : $this->users->findOneBy(['resetTokenHash' => hash('sha256', $token)]);

        if (null === $user || !$user->isResetTokenValid(new \DateTimeImmutable())) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_token', 'message' => 'El enlace no es válido o ha caducado. Pide uno nuevo.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->clearPasswordReset();
        $this->em->flush();

        return new JsonResponse(['data' => ['ok' => true]]);
    }

    private function neutral(): JsonResponse
    {
        return new JsonResponse(['data' => [
            'ok' => true,
            'message' => 'Si ese correo tiene una cuenta, te enviamos instrucciones para recuperarla.',
        ]]);
    }
}
