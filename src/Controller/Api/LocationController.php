<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\LocationPresenter;
use App\Repository\LocationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Lectura pública de las zonas de vuelo activas. */
#[Route('/api')]
final class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationRepository $locations,
        private readonly LocationPresenter $presenter,
    ) {
    }

    #[Route('/locations', name: 'api_locations_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $locations = $this->locations->findAllActiveOrdered();

        $response = new JsonResponse([
            'data' => $this->presenter->locations($locations),
            'meta' => ['total' => count($locations)],
        ]);
        $response->setPublic()->setMaxAge(300);

        return $response;
    }
}
