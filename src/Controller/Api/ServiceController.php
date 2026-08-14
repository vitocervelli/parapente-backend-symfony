<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ServicePresenter;
use App\Enum\ServiceType;
use App\Repository\InclusionItemRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Lectura pública del catálogo. */
#[Route('/api')]
final class ServiceController extends AbstractController
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly InclusionItemRepository $items,
        private readonly ServicePresenter $presenter,
    ) {
    }

    #[Route('/services', name: 'api_services_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $rawType = $request->query->get('type');
        $type = null;

        if (null !== $rawType && '' !== $rawType) {
            $type = ServiceType::tryFrom($rawType);
            if (null === $type) {
                return $this->error('invalid_type', 'El tipo debe ser "standalone" o "promotion".', Response::HTTP_BAD_REQUEST);
            }
        }

        // ?home=1 devuelve solo lo que va en el mosaico de la portada.
        $onlyHome = $request->query->getBoolean('home');

        // ?location=<slug> limita a los servicios de una zona activa.
        $rawLocation = $request->query->get('location');
        $locationSlug = (\is_string($rawLocation) && '' !== trim($rawLocation)) ? trim($rawLocation) : null;

        $services = $this->services->findPublished($type, $onlyHome, $locationSlug);

        return $this->cached([
            'data' => $this->presenter->services($services),
            'meta' => ['total' => count($services), 'type' => $type?->value, 'home' => $onlyHome, 'location' => $locationSlug],
        ]);
    }

    #[Route('/services/{slug}', name: 'api_services_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(string $slug): JsonResponse
    {
        $service = $this->services->findOnePublishedBySlug($slug);

        if (null === $service) {
            return $this->error('service_not_found', 'No existe un servicio con ese identificador.', Response::HTTP_NOT_FOUND);
        }

        return $this->cached(['data' => $this->presenter->service($service)]);
    }

    #[Route('/inclusion-items', name: 'api_inclusion_items_index', methods: ['GET'])]
    public function items(): JsonResponse
    {
        return $this->cached(['data' => $this->presenter->items($this->items->findAllOrdered())]);
    }

    /** @param array<string, mixed> $payload */
    private function cached(array $payload): JsonResponse
    {
        $response = new JsonResponse($payload);
        $response->setPublic()->setMaxAge(300);

        return $response;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
