<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Cifras públicas para la portada. */
#[Route('/api')]
final class StatsController extends AbstractController
{
    public function __construct(
        private readonly BookingRepository $bookings,
    ) {
    }

    #[Route('/stats', name: 'api_stats', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $response = new JsonResponse([
            'data' => [
                // Personas que han volado (reservas completadas), no nº de reservas.
                'peopleFlown' => $this->bookings->countPeopleFlown(),
            ],
        ]);

        // Se cachea unos minutos: es un número de marketing, no hace falta al día.
        $response->setPublic()->setMaxAge(300);

        return $response;
    }
}
