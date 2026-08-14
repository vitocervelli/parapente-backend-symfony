<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\SettingsPresenter;
use App\Repository\BookingSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Lectura pública de los ajustes que el asistente de reserva necesita. */
#[Route('/api')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly BookingSettingsRepository $settings,
        private readonly SettingsPresenter $presenter,
    ) {
    }

    #[Route('/settings', name: 'api_settings_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $response = new JsonResponse(['data' => $this->presenter->settings($this->settings->get())]);
        $response->setPublic()->setMaxAge(300);

        return $response;
    }
}
