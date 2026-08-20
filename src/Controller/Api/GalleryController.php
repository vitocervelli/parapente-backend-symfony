<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\GalleryPresenter;
use App\Repository\GalleryPhotoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Lectura pública de las fotos activas de la galería. */
#[Route('/api')]
final class GalleryController extends AbstractController
{
    public function __construct(
        private readonly GalleryPhotoRepository $photos,
        private readonly GalleryPresenter $presenter,
    ) {
    }

    #[Route('/gallery', name: 'api_gallery_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $photos = $this->photos->findAllActiveOrdered();

        $response = new JsonResponse([
            'data' => $this->presenter->photos($photos),
            'meta' => ['total' => count($photos)],
        ]);
        $response->setPublic()->setMaxAge(300);

        return $response;
    }
}
