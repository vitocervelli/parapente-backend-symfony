<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BookingPresenter;
use App\Booking\BookingException;
use App\Booking\BookingWorkflow;
use App\Entity\PaymentProof;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Storage\PrivateFileStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Comprobantes de pago: el cliente sube y consulta los de SUS reservas. */
#[Route('/api/account/bookings/{reference}/proofs', requirements: ['reference' => '[A-Z0-9-]+'])]
#[IsGranted('ROLE_CUSTOMER')]
final class AccountProofController extends AbstractController
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    /** Tope por envío; con post_max_size en 12M, más de esto no cabría igual. */
    private const MAX_FILES = 6;

    /**
     * Sin SVG a propósito: lo sube cualquier cliente, y un SVG con script es
     * XSS almacenado si algún día se sirve inline.
     */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly BookingWorkflow $workflow,
        private readonly PrivateFileStorage $storage,
        private readonly BookingPresenter $presenter,
    ) {
    }

    /**
     * El cliente solo sube imágenes: una o varias de golpe (un pago puede
     * partirse en transferencias). El importe y la referencia los registra el
     * administrador al revisarlas, que es quien tiene el extracto delante.
     */
    #[Route('', name: 'api_account_proofs_upload', methods: ['POST'])]
    public function upload(#[CurrentUser] User $customer, string $reference, Request $request): JsonResponse
    {
        $booking = $this->bookings->findOneForCustomer($reference, $customer);
        if (null === $booking) {
            return $this->fail('booking_not_found', 'No encontramos esa reserva.', Response::HTTP_NOT_FOUND);
        }

        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            array_merge($request->files->all('files'), array_filter([$request->files->get('file')])),
            static fn ($f) => $f instanceof UploadedFile,
        ));

        if ([] === $files) {
            // También pasa cuando el POST supera post_max_size: PHP lo descarta
            // entero sin avisar.
            return $this->fail('no_file', 'No llegó ningún archivo. Si las fotos pesan mucho, sube menos de una vez.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($files) > self::MAX_FILES) {
            return $this->fail('too_many_files', sprintf('Sube como mucho %d archivos a la vez.', self::MAX_FILES), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Se valida TODO antes de guardar nada: así una subida múltiple no deja
        // la mitad de los ficheros dentro y la otra mitad fuera.
        foreach ($files as $i => $file) {
            $posicion = $i + 1;

            if ($file->getSize() > self::MAX_BYTES) {
                return $this->fail('file_too_large', sprintf('El archivo nº %d supera los 8 MB.', $posicion), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!isset(self::ALLOWED_MIME[(string) $file->getMimeType()])) {
                return $this->fail('invalid_type', sprintf('El archivo nº %d no es una foto ni un PDF.', $posicion), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            foreach ($files as $file) {
                $mime = (string) $file->getMimeType();

                // Los metadatos se leen ANTES de guardar: store() mueve el
                // fichero temporal y después ya no se puede consultar su tamaño.
                $sizeBytes = (int) $file->getSize();
                $originalName = substr($file->getClientOriginalName(), 0, 200);

                $stored = $this->storage->store($file, 'proofs', self::ALLOWED_MIME[$mime]);

                $this->workflow->submitProof($booking, (new PaymentProof())
                    ->setStoragePath($stored['path'])
                    ->setChecksum($stored['checksum'])
                    ->setOriginalName($originalName)
                    ->setMimeType($mime)
                    ->setSizeBytes($sizeBytes));
            }
        } catch (BookingException $e) {
            return $this->fail($e->errorCode, $e->getMessage(), $e->statusCode);
        }

        return new JsonResponse(
            ['data' => $this->presenter->booking($booking)],
            Response::HTTP_CREATED,
        );
    }

    // "download" y no "file": AbstractController ya tiene un método file() con
    // otra firma y PHP no permite redefinirlo.
    #[Route('/{id}/file', name: 'api_account_proofs_file', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(#[CurrentUser] User $customer, string $reference, int $id): Response
    {
        $booking = $this->bookings->findOneForCustomer($reference, $customer);
        if (null === $booking) {
            return $this->fail('booking_not_found', 'No encontramos esa reserva.', Response::HTTP_NOT_FOUND);
        }

        $proof = null;
        foreach ($booking->getProofs() as $candidate) {
            if ($candidate->getId() === $id) {
                $proof = $candidate;
                break;
            }
        }

        if (null === $proof || !$this->storage->exists($proof->getStoragePath())) {
            return $this->fail('proof_not_found', 'No encontramos ese comprobante.', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($this->storage->absolutePath($proof->getStoragePath()));
        // Content-Type de la lista blanca (validado al subir), nunca adivinado.
        $response->headers->set('Content-Type', $proof->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition('inline', 'comprobante-' . $proof->getId() . '.' . pathinfo($proof->getStoragePath(), PATHINFO_EXTENSION));
        $response->setPrivate();

        return $response;
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
