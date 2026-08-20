<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\LocationPresenter;
use App\Entity\Location;
use App\Repository\LocationRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Mantenimiento de las localidades (zonas de vuelo). Requiere token JWT con ROLE_ADMIN. */
#[Route('/api/admin/locations')]
#[IsGranted('ROLE_ADMIN')]
final class AdminLocationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LocationRepository $locations,
        private readonly LocationPresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_locations_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->locations($this->locations->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_locations_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $location = new Location();
        $this->em->persist($location);

        return $this->save($location, $request, Response::HTTP_CREATED);
    }

    #[Route('/reorder', name: 'api_admin_locations_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $order = $payload['order'] ?? null;
        if (!\is_array($order) || [] === $order) {
            return new JsonResponse(['errors' => ['order' => 'Envía un array con los ids en el orden deseado.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $positionsById = [];
        foreach (array_values($order) as $position => $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                return new JsonResponse(['errors' => ['order' => 'Todos los ids deben ser números positivos.']], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $positionsById[$id] = $position;
        }

        $updated = $this->locations->applyOrder($positionsById);
        $this->em->flush();

        return new JsonResponse(['data' => ['updated' => $updated]]);
    }

    #[Route('/{id}', name: 'api_admin_locations_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $location = $this->locations->find($id);

        if (null === $location) {
            return $this->notFound();
        }

        $location->touch();

        return $this->save($location, $request, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_admin_locations_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $location = $this->locations->find($id);

        if (null === $location) {
            return $this->notFound();
        }

        // Una zona con servicios asignados no se borra: dejaría servicios huérfanos
        // (y el borrado en cascada del vínculo los quitaría de la zona sin avisar).
        if ($location->getServices()->count() > 0) {
            return new JsonResponse(
                ['error' => ['code' => 'location_in_use', 'message' => 'Esa zona tiene servicios asignados. Quítalos antes de borrarla.']],
                Response::HTTP_CONFLICT,
            );
        }

        $this->em->remove($location);

        try {
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            // La FK availability_slot → location es RESTRICT: si tiene franjas, no se borra.
            return new JsonResponse(
                ['error' => ['code' => 'location_in_use', 'message' => 'Esa zona tiene disponibilidad creada. Bórrala antes de eliminar la zona.']],
                Response::HTTP_CONFLICT,
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function save(Location $location, Request $request, int $successStatus): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (\array_key_exists('slug', $payload)) {
            $location->setSlug(trim((string) $payload['slug']));
        }

        if (\array_key_exists('name', $payload)) {
            $location->setName(trim((string) $payload['name']));
        }

        foreach (['region', 'badge', 'description'] as $field) {
            if (\array_key_exists($field, $payload)) {
                $raw = $payload[$field];
                $value = (null === $raw || '' === trim((string) $raw)) ? null : trim((string) $raw);
                $location->{'set' . ucfirst($field)}($value);
            }
        }

        if (\array_key_exists('position', $payload)) {
            $location->setPosition((int) $payload['position']);
        }

        if (\array_key_exists('isActive', $payload)) {
            $location->setIsActive((bool) $payload['isActive']);
        }

        $violations = $this->validator->validate($location);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->location($location)], $successStatus);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'location_not_found', 'message' => 'No existe esa localidad.']],
            Response::HTTP_NOT_FOUND,
        );
    }
}
