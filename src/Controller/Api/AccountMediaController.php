<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\BookingRepository;
use App\Storage\PrivateFileStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Los recuerdos del vuelo, para su dueño. La lista llega embebida en la
 * reserva (BookingPresenter); aquí solo se sirve el fichero.
 *
 * Doble capa de autorización, como los comprobantes: la reserva se busca
 * SIEMPRE filtrada por el cliente autenticado, y el archivo se localiza
 * iterando la colección de esa reserva — nunca por id suelto en el repositorio.
 */
#[Route('/api/account/bookings/{reference}/media', requirements: ['reference' => '[A-Z0-9-]+'])]
#[IsGranted('ROLE_CUSTOMER')]
final class AccountMediaController extends AbstractController
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly PrivateFileStorage $storage,
    ) {
    }

    #[Route('/{id}/file', name: 'api_account_media_file', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(#[CurrentUser] User $customer, string $reference, int $id, Request $request): Response
    {
        $booking = $this->bookings->findOneForCustomer($reference, $customer);
        if (null === $booking) {
            return $this->fail('booking_not_found', 'No existe esa reserva.', Response::HTTP_NOT_FOUND);
        }

        $media = null;
        foreach ($booking->getMedia() as $candidate) {
            if ($candidate->getId() === $id) {
                $media = $candidate;
                break;
            }
        }

        if (null === $media || !$this->storage->exists($media->getStoragePath())) {
            return $this->fail('media_not_found', 'Ese archivo no existe.', Response::HTTP_NOT_FOUND);
        }

        // BinaryFileResponse gestiona Range (206): el seek de los vídeos funciona solo.
        $response = new BinaryFileResponse($this->storage->absolutePath($media->getStoragePath()));
        $response->headers->set('Content-Type', $media->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ?download=1 fuerza la descarga con el nombre original del archivo;
        // sin él se muestra inline (miniaturas, reproductor).
        $fallback = 'vuelo-' . $media->getId() . '.' . pathinfo($media->getStoragePath(), PATHINFO_EXTENSION);
        if ($request->query->getBoolean('download')) {
            $original = str_replace(['/', '\\', '%', '"'], '-', $media->getOriginalName());
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, '' !== $original ? $original : $fallback, $fallback);
        } else {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $fallback);
        }
        $response->setPrivate();

        return $response;
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
