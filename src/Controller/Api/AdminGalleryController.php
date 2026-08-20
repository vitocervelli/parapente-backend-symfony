<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\GalleryPresenter;
use App\Entity\GalleryPhoto;
use App\Repository\GalleryPhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Mantenimiento de la galería pública. Requiere token JWT con ROLE_ADMIN. */
#[Route('/api/admin/gallery')]
#[IsGranted('ROLE_ADMIN')]
final class AdminGalleryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GalleryPhotoRepository $photos,
        private readonly GalleryPresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_gallery_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->photos($this->photos->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_gallery_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $photo = new GalleryPhoto();
        $this->em->persist($photo);

        return $this->save($photo, $request, Response::HTTP_CREATED);
    }

    #[Route('/reorder', name: 'api_admin_gallery_reorder', methods: ['POST'])]
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

        $updated = $this->photos->applyOrder($positionsById);
        $this->em->flush();

        return new JsonResponse(['data' => ['updated' => $updated]]);
    }

    #[Route('/{id}', name: 'api_admin_gallery_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $photo = $this->photos->find($id);

        if (null === $photo) {
            return $this->notFound();
        }

        return $this->save($photo, $request, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_admin_gallery_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $photo = $this->photos->find($id);

        if (null === $photo) {
            return $this->notFound();
        }

        // El fichero de public/uploads/gallery queda huérfano; es inocuo y la
        // misma imagen puede reutilizarse en otra foto de la galería.
        $this->em->remove($photo);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function save(GalleryPhoto $photo, Request $request, int $successStatus): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (\array_key_exists('imagePath', $payload)) {
            $photo->setImagePath(trim((string) $payload['imagePath']));
        }

        if (\array_key_exists('alt', $payload)) {
            $photo->setAlt(trim((string) $payload['alt']));
        }

        if (\array_key_exists('isFeatured', $payload)) {
            $photo->setIsFeatured((bool) $payload['isFeatured']);
        }

        if (\array_key_exists('isWide', $payload)) {
            $photo->setIsWide((bool) $payload['isWide']);
        }

        if (\array_key_exists('position', $payload)) {
            $photo->setPosition((int) $payload['position']);
        }

        if (\array_key_exists('isActive', $payload)) {
            $photo->setIsActive((bool) $payload['isActive']);
        }

        $violations = $this->validator->validate($photo);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->photo($photo)], $successStatus);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'photo_not_found', 'message' => 'No existe esa foto.']],
            Response::HTTP_NOT_FOUND,
        );
    }
}
