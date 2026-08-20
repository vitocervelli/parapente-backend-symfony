<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BookingPresenter;
use App\Entity\Booking;
use App\Entity\BookingMedia;
use App\Enum\MediaKind;
use App\Repository\BookingRepository;
use App\Storage\PrivateFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Galería del vuelo de una reserva: el equipo sube fotos y vídeos y el cliente
 * los ve desde su cuenta (AccountMediaController).
 *
 * Las fotos llegan ya comprimidas desde el panel (el navegador las reduce a
 * ~2000 px antes de subir); el tope de 10 MB es la red de seguridad por si esa
 * compresión falla. Los vídeos no se pueden recomprimir en el servidor (no hay
 * ffmpeg), así que se aceptan tal cual hasta 100 MB.
 */
#[Route('/api/admin/bookings/{id}/media', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminBookingMediaController extends AbstractController
{
    private const IMAGE_MAX_BYTES = 10 * 1024 * 1024;
    private const VIDEO_MAX_BYTES = 100 * 1024 * 1024;
    private const MAX_FILES = 10;

    /** Sin SVG, como en los comprobantes (XSS almacenado). */
    private const ALLOWED_MIME = [
        'image/jpeg' => ['ext' => 'jpg', 'kind' => MediaKind::Image],
        'image/png' => ['ext' => 'png', 'kind' => MediaKind::Image],
        'image/webp' => ['ext' => 'webp', 'kind' => MediaKind::Image],
        'video/mp4' => ['ext' => 'mp4', 'kind' => MediaKind::Video],
        'video/quicktime' => ['ext' => 'mov', 'kind' => MediaKind::Video],
        'video/webm' => ['ext' => 'webm', 'kind' => MediaKind::Video],
    ];

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly BookingPresenter $presenter,
        private readonly PrivateFileStorage $storage,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'api_admin_media_upload', methods: ['POST'])]
    public function upload(int $id, Request $request): JsonResponse
    {
        $booking = $this->bookings->find($id);
        if (null === $booking) {
            return $this->notFound();
        }

        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            array_merge($request->files->all('files'), array_filter([$request->files->get('file')])),
            static fn ($f) => $f instanceof UploadedFile,
        ));

        if ([] === $files) {
            return $this->fail('no_file', 'No llegó ningún archivo. Si pesan mucho, sube menos de una vez.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($files) > self::MAX_FILES) {
            return $this->fail('too_many_files', sprintf('Sube como mucho %d archivos a la vez.', self::MAX_FILES), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Todo se valida antes de guardar nada: una subida múltiple no puede
        // dejar la mitad de los ficheros dentro y la otra fuera.
        foreach ($files as $i => $file) {
            $mime = (string) $file->getMimeType();
            if (!isset(self::ALLOWED_MIME[$mime])) {
                return $this->fail('invalid_type', sprintf('El archivo nº %d no es una foto (JPG/PNG/WebP) ni un vídeo (MP4/MOV/WebM).', $i + 1), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $max = MediaKind::Video === self::ALLOWED_MIME[$mime]['kind'] ? self::VIDEO_MAX_BYTES : self::IMAGE_MAX_BYTES;
            if ($file->getSize() > $max) {
                return $this->fail('file_too_large', sprintf('El archivo nº %d supera los %d MB.', $i + 1, (int) ($max / 1024 / 1024)), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        foreach ($files as $file) {
            // Tamaño, MIME y nombre se leen ANTES de store(): move() invalida el temporal.
            $mime = (string) $file->getMimeType();
            $sizeBytes = (int) $file->getSize();
            $originalName = substr($file->getClientOriginalName(), 0, 200);
            $rule = self::ALLOWED_MIME[$mime];

            $stored = $this->storage->store($file, 'media', $rule['ext']);

            $booking->addMedia((new BookingMedia())
                ->setKind($rule['kind'])
                ->setStoragePath($stored['path'])
                ->setChecksum($stored['checksum'])
                ->setOriginalName($originalName)
                ->setMimeType($mime)
                ->setSizeBytes($sizeBytes));
        }

        // Un único flush: la subida múltiple es atómica en base de datos.
        $this->em->flush();

        return new JsonResponse(
            ['data' => $this->presenter->booking($booking, forAdmin: true)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{mediaId}', name: 'api_admin_media_delete', methods: ['DELETE'], requirements: ['mediaId' => '\d+'])]
    public function delete(int $id, int $mediaId): JsonResponse
    {
        $found = $this->findMedia($id, $mediaId);
        if ($found instanceof JsonResponse) {
            return $found;
        }
        [$booking, $media] = $found;

        $path = $media->getStoragePath();

        // Primero la base de datos, después el disco: si el flush fallara con el
        // fichero ya borrado, quedaría una fila apuntando a la nada (404 visible
        // para el cliente). Un fichero huérfano en var/uploads es inocuo.
        $booking->removeMedia($media);
        $this->em->flush();

        try {
            $this->storage->delete($path);
        } catch (\RuntimeException $e) {
            $this->logger->warning('No se pudo borrar el fichero de la galería.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return new JsonResponse(['data' => $this->presenter->booking($booking, forAdmin: true)]);
    }

    /** Llamado download y no file: AbstractController::file() ya existe. */
    #[Route('/{mediaId}/file', name: 'api_admin_media_file', methods: ['GET'], requirements: ['mediaId' => '\d+'])]
    public function download(int $id, int $mediaId, Request $request): Response
    {
        $found = $this->findMedia($id, $mediaId);
        if ($found instanceof JsonResponse) {
            return $found;
        }
        [, $media] = $found;

        if (!$this->storage->exists($media->getStoragePath())) {
            return $this->fail('media_not_found', 'El archivo no está en el almacén.', Response::HTTP_NOT_FOUND);
        }

        // BinaryFileResponse gestiona Range (206) por sí sola: el seek de los
        // vídeos funciona sin más.
        $response = new BinaryFileResponse($this->storage->absolutePath($media->getStoragePath()));
        $response->headers->set('Content-Type', $media->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ?download=1 fuerza la descarga con el nombre original del archivo;
        // sin él se muestra inline (miniaturas, reproductor).
        $fallback = 'media-' . $media->getId() . '.' . pathinfo($media->getStoragePath(), PATHINFO_EXTENSION);
        if ($request->query->getBoolean('download')) {
            $original = str_replace(['/', '\\', '%', '"'], '-', $media->getOriginalName());
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, '' !== $original ? $original : $fallback, $fallback);
        } else {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $fallback);
        }
        $response->setPrivate();

        return $response;
    }

    /** @return JsonResponse|array{Booking, BookingMedia} */
    private function findMedia(int $bookingId, int $mediaId): JsonResponse|array
    {
        $booking = $this->bookings->find($bookingId);
        if (null === $booking) {
            return $this->notFound();
        }

        foreach ($booking->getMedia() as $media) {
            if ($media->getId() === $mediaId) {
                return [$booking, $media];
            }
        }

        return $this->fail('media_not_found', 'Esa reserva no tiene ese archivo.', Response::HTTP_NOT_FOUND);
    }

    private function notFound(): JsonResponse
    {
        return $this->fail('booking_not_found', 'No existe esa reserva.', Response::HTTP_NOT_FOUND);
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
