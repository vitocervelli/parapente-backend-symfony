<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ServicePresenter;
use App\Entity\Extra;
use App\Enum\Currency;
use App\Repository\ExtraRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Mantenimiento del catálogo de extras de pago. Requiere token JWT con ROLE_ADMIN. */
#[Route('/api/admin/extras')]
#[IsGranted('ROLE_ADMIN')]
final class AdminExtraController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExtraRepository $extras,
        private readonly ServicePresenter $presenter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_extras_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->presenter->extras($this->extras->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_extras_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $extra = new Extra();
        $this->em->persist($extra);

        return $this->save($extra, $request, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_extras_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $extra = $this->extras->find($id);

        if (null === $extra) {
            return $this->notFound();
        }

        return $this->save($extra, $request, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_admin_extras_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $extra = $this->extras->find($id);

        if (null === $extra) {
            return $this->notFound();
        }

        $this->em->remove($extra);

        try {
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            // La FK service_extra → extra es RESTRICT: un extra en uso no debe
            // desaparecer de los servicios que ya lo ofrecen.
            return new JsonResponse(
                ['error' => ['code' => 'extra_in_use', 'message' => 'Ese extra se usa en algún servicio. Quítalo de ahí antes de borrarlo.']],
                Response::HTTP_CONFLICT,
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function save(Extra $extra, Request $request, int $successStatus): JsonResponse
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
            $extra->setSlug(trim((string) $payload['slug']));
        }

        if (\array_key_exists('name', $payload)) {
            $extra->setName(trim((string) $payload['name']));
        }

        if (\array_key_exists('priceAmount', $payload)) {
            $amount = (string) $payload['priceAmount'];
            if (1 !== preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount)) {
                return $this->invalid(['priceAmount' => 'Usa un importe como 20 o 20.50.']);
            }
            $extra->setPriceAmount(number_format((float) $amount, 2, '.', ''));
        }

        if (\array_key_exists('currency', $payload)) {
            $currency = Currency::tryFrom((string) $payload['currency']);
            if (null === $currency) {
                return $this->invalid(['currency' => 'Debe ser "USD" o "EUR".']);
            }
            $extra->setCurrency($currency);
        }

        if (\array_key_exists('icon', $payload)) {
            $extra->setIcon(trim((string) $payload['icon']) ?: 'check');
        }

        if (\array_key_exists('note', $payload)) {
            $raw = $payload['note'];
            $extra->setNote((null === $raw || '' === trim((string) $raw)) ? null : trim((string) $raw));
        }

        if (\array_key_exists('position', $payload)) {
            $extra->setPosition((int) $payload['position']);
        }

        if (\array_key_exists('isActive', $payload)) {
            $extra->setIsActive((bool) $payload['isActive']);
        }

        $violations = $this->validator->validate($extra);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return new JsonResponse(['data' => $this->presenter->extra($extra)], $successStatus);
    }

    /** @param array<string, string> $errors */
    private function invalid(array $errors): JsonResponse
    {
        return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'extra_not_found', 'message' => 'No existe ese extra.']],
            Response::HTTP_NOT_FOUND,
        );
    }
}
