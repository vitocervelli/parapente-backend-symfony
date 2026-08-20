<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ReelPresenter;
use App\Repository\ReelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Lectura pública de los reels activos, para la portada. */
#[Route('/api')]
final class ReelController extends AbstractController
{
    public function __construct(
        private readonly ReelRepository $reels,
        private readonly ReelPresenter $presenter,
    ) {
    }

    #[Route('/reels', name: 'api_reels_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $reels = $this->reels->findAllActiveOrdered();

        $response = new JsonResponse([
            'data' => $this->presenter->reels($reels),
            'meta' => ['total' => count($reels)],
        ]);
        $response->setPublic()->setMaxAge(300);

        return $response;
    }
}
