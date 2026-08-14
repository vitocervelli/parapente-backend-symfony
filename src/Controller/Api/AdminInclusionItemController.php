<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ServicePresenter;
use App\Entity\InclusionItem;
use App\Repository\InclusionItemRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Mantenimiento del catálogo de elementos incluidos. */
#[Route('/api/admin/inclusion-items')]
#[IsGranted('ROLE_ADMIN')]
final class AdminInclusionItemController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InclusionItemRepository $items,
        private readonly ServicePresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_items_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->items($this->items->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_items_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $item = new InclusionItem();
        $this->em->persist($item);

        return $this->save($item, $request, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_items_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->items->find($id);

        if (null === $item) {
            return $this->notFound();
        }

        return $this->save($item, $request, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_admin_items_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->items->find($id);

        if (null === $item) {
            return $this->notFound();
        }

        $this->em->remove($item);

        try {
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            // La FK es RESTRICT a propósito: un elemento en uso no debe desaparecer
            // de las promociones que ya lo incluyen.
            return new JsonResponse(
                ['error' => ['code' => 'item_in_use', 'message' => 'Ese elemento se usa en algún servicio. Quítalo de ahí antes de borrarlo.']],
                Response::HTTP_CONFLICT,
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function save(InclusionItem $item, Request $request, int $successStatus): JsonResponse
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
            $item->setSlug(trim((string) $payload['slug']));
        }

        if (\array_key_exists('defaultLabel', $payload)) {
            $item->setDefaultLabel(trim((string) $payload['defaultLabel']));
        }

        if (\array_key_exists('icon', $payload)) {
            $item->setIcon(trim((string) $payload['icon']) ?: 'check');
        }

        if (\array_key_exists('iconPath', $payload)) {
            $raw = $payload['iconPath'];
            $item->setIconPath((null === $raw || '' === trim((string) $raw)) ? null : trim((string) $raw));
        }

        if (\array_key_exists('position', $payload)) {
            $item->setPosition((int) $payload['position']);
        }

        $violations = $this->validator->validate($item);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->item($item)], $successStatus);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'item_not_found', 'message' => 'No existe ese elemento.']],
            Response::HTTP_NOT_FOUND,
        );
    }
}
