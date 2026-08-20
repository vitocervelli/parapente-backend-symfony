<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\AllyPresenter;
use App\Entity\Ally;
use App\Repository\AllyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Mantenimiento de los aliados de la portada. Requiere token JWT con ROLE_ADMIN. */
#[Route('/api/admin/allies')]
#[IsGranted('ROLE_ADMIN')]
final class AdminAllyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AllyRepository $allies,
        private readonly AllyPresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_allies_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->allies($this->allies->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_allies_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $ally = new Ally();
        $this->em->persist($ally);

        return $this->save($ally, $request, Response::HTTP_CREATED);
    }

    #[Route('/reorder', name: 'api_admin_allies_reorder', methods: ['POST'])]
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

        $updated = $this->allies->applyOrder($positionsById);
        $this->em->flush();

        return new JsonResponse(['data' => ['updated' => $updated]]);
    }

    #[Route('/{id}', name: 'api_admin_allies_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $ally = $this->allies->find($id);

        if (null === $ally) {
            return $this->notFound();
        }

        return $this->save($ally, $request, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_admin_allies_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $ally = $this->allies->find($id);

        if (null === $ally) {
            return $this->notFound();
        }

        $this->em->remove($ally);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function save(Ally $ally, Request $request, int $successStatus): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_json', 'message' => 'El cuerpo de la petición no es JSON válido.']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (\array_key_exists('name', $payload)) {
            $ally->setName(trim((string) $payload['name']));
        }

        foreach (['kind', 'logoPath'] as $field) {
            if (\array_key_exists($field, $payload)) {
                $raw = $payload[$field];
                $value = (null === $raw || '' === trim((string) $raw)) ? null : trim((string) $raw);
                $ally->{'set' . ucfirst($field)}($value);
            }
        }

        if (\array_key_exists('position', $payload)) {
            $ally->setPosition((int) $payload['position']);
        }

        if (\array_key_exists('isActive', $payload)) {
            $ally->setIsActive((bool) $payload['isActive']);
        }

        $violations = $this->validator->validate($ally);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->ally($ally)], $successStatus);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'ally_not_found', 'message' => 'No existe ese aliado.']],
            Response::HTTP_NOT_FOUND,
        );
    }
}
