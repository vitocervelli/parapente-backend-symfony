<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ReelPresenter;
use App\Entity\Reel;
use App\Repository\ReelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Mantenimiento de los reels de la portada. Requiere token JWT con ROLE_ADMIN. */
#[Route('/api/admin/reels')]
#[IsGranted('ROLE_ADMIN')]
final class AdminReelController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReelRepository $reels,
        private readonly ReelPresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_reels_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->reels($this->reels->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_reels_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $reel = new Reel();
        $this->em->persist($reel);

        return $this->save($reel, $request, Response::HTTP_CREATED);
    }

    #[Route('/reorder', name: 'api_admin_reels_reorder', methods: ['POST'])]
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

        $updated = $this->reels->applyOrder($positionsById);
        $this->em->flush();

        return new JsonResponse(['data' => ['updated' => $updated]]);
    }

    #[Route('/{id}', name: 'api_admin_reels_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $reel = $this->reels->find($id);

        if (null === $reel) {
            return $this->notFound();
        }

        return $this->save($reel, $request, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_admin_reels_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $reel = $this->reels->find($id);

        if (null === $reel) {
            return $this->notFound();
        }

        // El fichero de vídeo en public/uploads/reels queda huérfano; es inocuo.
        $this->em->remove($reel);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function save(Reel $reel, Request $request, int $successStatus): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (\array_key_exists('videoPath', $payload)) {
            $reel->setVideoPath(trim((string) $payload['videoPath']));
        }

        foreach (['posterPath', 'caption'] as $field) {
            if (\array_key_exists($field, $payload)) {
                $raw = $payload[$field];
                $value = (null === $raw || '' === trim((string) $raw)) ? null : trim((string) $raw);
                $reel->{'set' . ucfirst($field)}($value);
            }
        }

        if (\array_key_exists('position', $payload)) {
            $reel->setPosition((int) $payload['position']);
        }

        if (\array_key_exists('isActive', $payload)) {
            $reel->setIsActive((bool) $payload['isActive']);
        }

        $violations = $this->validator->validate($reel);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->reel($reel)], $successStatus);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'reel_not_found', 'message' => 'No existe ese reel.']],
            Response::HTTP_NOT_FOUND,
        );
    }
}
