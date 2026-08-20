<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\AllyPresenter;
use App\Repository\AllyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Lectura pública de los aliados activos, para la portada. */
#[Route('/api')]
final class AllyController extends AbstractController
{
    public function __construct(
        private readonly AllyRepository $allies,
        private readonly AllyPresenter $presenter,
    ) {
    }

    #[Route('/allies', name: 'api_allies_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $allies = $this->allies->findAllActiveOrdered();

        $response = new JsonResponse([
            'data' => $this->presenter->allies($allies),
            'meta' => ['total' => count($allies)],
        ]);
        $response->setPublic()->setMaxAge(300);

        return $response;
    }
}
