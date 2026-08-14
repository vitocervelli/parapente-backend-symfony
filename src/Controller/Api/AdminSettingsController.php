<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\SettingsPresenter;
use App\Entity\BookingSettings;
use App\Enum\Currency;
use App\Repository\BookingSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Ajustes globales de reserva. Requiere token JWT con ROLE_ADMIN. */
#[Route('/api/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
final class AdminSettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookingSettingsRepository $settings,
        private readonly SettingsPresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_settings_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->settings($this->settings->get())]);
    }

    #[Route('', name: 'api_admin_settings_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $settings = $this->settings->get();
        // Si aún no existe la fila (base recién creada), se persiste ahora.
        if (null === $settings->getId()) {
            $this->em->persist($settings);
        }

        if (\array_key_exists('companionFeeAmount', $payload)) {
            $amount = (string) $payload['companionFeeAmount'];
            if (1 !== preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount)) {
                return $this->invalid(['companionFeeAmount' => 'Usa un importe como 5 o 5.50.']);
            }
            $settings->setCompanionFeeAmount(number_format((float) $amount, 2, '.', ''));
        }

        if (\array_key_exists('companionFeeCurrency', $payload)) {
            $currency = Currency::tryFrom((string) $payload['companionFeeCurrency']);
            if (null === $currency) {
                return $this->invalid(['companionFeeCurrency' => 'Debe ser "USD" o "EUR".']);
            }
            $settings->setCompanionFeeCurrency($currency);
        }

        if (\array_key_exists('weekdayFreePerFlyer', $payload)) {
            $settings->setWeekdayFreePerFlyer((int) $payload['weekdayFreePerFlyer']);
        }

        $violations = $this->validator->validate($settings);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->invalid($errors);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->settings($settings)]);
    }

    /** @param array<string, string> $errors */
    private function invalid(array $errors): JsonResponse
    {
        return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
