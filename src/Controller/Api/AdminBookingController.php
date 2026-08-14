<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BookingPresenter;
use App\Booking\BookingCreator;
use App\Booking\BookingEditor;
use App\Booking\BookingException;
use App\Booking\BookingWorkflow;
use App\Booking\CustomerProvisioner;
use App\Entity\Booking;
use App\Entity\PaymentProof;
use App\Entity\User;
use App\Enum\BookingStatus;
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

/** Bandeja de reservas del panel: revisar comprobantes y decidir. */
#[Route('/api/admin/bookings')]
#[IsGranted('ROLE_ADMIN')]
final class AdminBookingController extends AbstractController
{
    private const PROOF_MAX_BYTES = 8 * 1024 * 1024;
    private const PROOF_MAX_FILES = 6;

    /** Misma lista blanca que la subida del cliente: sin SVG (XSS almacenado). */
    private const PROOF_ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly BookingWorkflow $workflow,
        private readonly BookingEditor $editor,
        private readonly BookingCreator $creator,
        private readonly CustomerProvisioner $customers,
        private readonly BookingPresenter $presenter,
        private readonly PrivateFileStorage $storage,
    ) {
    }

    /**
     * Alta de una reserva desde el panel (p. ej. un cliente que llama por
     * teléfono). Se resuelve o crea el cliente por su correo y se reutiliza el
     * mismo motor que la web: respeta el cupo y nace en «pendiente de pago».
     */
    #[Route('', name: 'api_admin_bookings_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->fail('invalid_json', 'El cuerpo de la petición no es JSON válido.', Response::HTTP_BAD_REQUEST);
        }

        $lines = $payload['lines'] ?? null;
        if (!\is_array($lines) || [] === $lines) {
            return $this->fail('invalid_booking', 'Añade al menos un vuelo a la reserva.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $customerData = \is_array($payload['customer'] ?? null) ? $payload['customer'] : [];

        try {
            $customer = $this->customers->findOrCreate(
                (string) ($customerData['email'] ?? ''),
                $this->nullableString($customerData['fullName'] ?? null),
                $this->nullableString($customerData['phone'] ?? null),
            );

            $booking = $this->creator->create(
                $customer,
                $lines,
                $this->nullableString($payload['contactPhone'] ?? null),
                $this->nullableString($payload['note'] ?? null),
            );
        } catch (BookingException $e) {
            return new JsonResponse([
                'error' => array_filter([
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'context' => $e->context ?: null,
                ]),
            ], $e->statusCode);
        }

        return new JsonResponse(
            ['data' => $this->presenter->booking($booking, forAdmin: true)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('', name: 'api_admin_bookings_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        // «Vencidas»: reservas que aún retienen plazas pero ya pasaron su plazo
        // de pago (o su fecha de vuelo). No es un estado, es una condición, así
        // que se pide con scope y no con status.
        if ('overdue' === $request->query->get('scope')) {
            $bookings = $this->bookings->findExpirable(new \DateTimeImmutable(), new \DateTimeImmutable('today'));

            return new JsonResponse([
                'data' => $this->presenter->bookings($bookings, forAdmin: true),
                'meta' => ['total' => count($bookings), 'scope' => 'overdue'],
            ]);
        }

        $statuses = [];
        foreach (explode(',', (string) $request->query->get('status', '')) as $raw) {
            $status = BookingStatus::tryFrom(trim($raw));
            if (null !== $status) {
                $statuses[] = $status;
            }
        }

        $bookings = $this->bookings->findForAdmin($statuses);

        return new JsonResponse([
            'data' => $this->presenter->bookings($bookings, forAdmin: true),
            'meta' => [
                'total' => count($bookings),
                'status' => array_map(fn (BookingStatus $s) => $s->value, $statuses),
            ],
        ]);
    }

    #[Route('/{id}', name: 'api_admin_bookings_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $booking = $this->bookings->find($id);
        if (null === $booking) {
            return $this->notFound();
        }

        return new JsonResponse(['data' => $this->presenter->booking($booking, forAdmin: true)]);
    }

    #[Route('/{id}', name: 'api_admin_bookings_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $booking = $this->bookings->find($id);
        if (null === $booking) {
            return $this->notFound();
        }

        try {
            $this->editor->update($booking, $this->readPayload($request));
        } catch (BookingException $e) {
            return $this->fail($e->errorCode, $e->getMessage(), $e->statusCode);
        }

        return new JsonResponse(['data' => $this->presenter->booking($booking, forAdmin: true)]);
    }

    #[Route('/{id}/proofs', name: 'api_admin_proof_upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function uploadProof(int $id, Request $request): JsonResponse
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
            return $this->fail('no_file', 'No llegó ningún archivo. Si las fotos pesan mucho, sube menos de una vez.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($files) > self::PROOF_MAX_FILES) {
            return $this->fail('too_many_files', sprintf('Sube como mucho %d archivos a la vez.', self::PROOF_MAX_FILES), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Todo se valida antes de guardar: una subida múltiple no puede dejar la
        // mitad de los ficheros dentro y la otra fuera.
        foreach ($files as $i => $file) {
            if ($file->getSize() > self::PROOF_MAX_BYTES) {
                return $this->fail('file_too_large', sprintf('El archivo nº %d supera los 8 MB.', $i + 1), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if (!isset(self::PROOF_ALLOWED_MIME[(string) $file->getMimeType()])) {
                return $this->fail('invalid_type', sprintf('El archivo nº %d no es una foto ni un PDF.', $i + 1), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            foreach ($files as $file) {
                $mime = (string) $file->getMimeType();
                $sizeBytes = (int) $file->getSize();
                $originalName = substr($file->getClientOriginalName(), 0, 200);

                $stored = $this->storage->store($file, 'proofs', self::PROOF_ALLOWED_MIME[$mime]);

                $this->workflow->addProofByAdmin($booking, (new PaymentProof())
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
            ['data' => $this->presenter->booking($booking, forAdmin: true)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/proofs/{proofId}', name: 'api_admin_proof_update', methods: ['PATCH'], requirements: ['id' => '\d+', 'proofId' => '\d+'])]
    public function updateProof(int $id, int $proofId, Request $request): JsonResponse
    {
        $found = $this->findProof($id, $proofId);
        if ($found instanceof JsonResponse) {
            return $found;
        }
        [$booking, $proof] = $found;

        $payload = $this->readPayload($request);

        $amount = null;
        $rawAmount = trim((string) ($payload['declaredAmount'] ?? ''));
        if ('' !== $rawAmount) {
            if (1 !== preg_match('/^\d{1,8}([.,]\d{1,2})?$/', $rawAmount)) {
                return $this->fail('invalid_amount', 'El importe no tiene un formato válido.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $amount = number_format((float) str_replace(',', '.', $rawAmount), 2, '.', '');
        }

        try {
            $this->workflow->adjustReviewedProof(
                $proof,
                $amount,
                $this->stringOrNull($payload['transferReference'] ?? null, 80),
                $this->stringOrNull($payload['note'] ?? null),
            );
        } catch (BookingException $e) {
            return $this->fail($e->errorCode, $e->getMessage(), $e->statusCode);
        }

        return new JsonResponse(['data' => $this->presenter->booking($booking, forAdmin: true)]);
    }

    #[Route('/{id}/proofs/{proofId}/accept', name: 'api_admin_proof_accept', methods: ['POST'], requirements: ['id' => '\d+', 'proofId' => '\d+'])]
    public function acceptProof(#[CurrentUser] User $admin, int $id, int $proofId, Request $request): JsonResponse
    {
        return $this->reviewProof($admin, $id, $proofId, $request, accept: true);
    }

    #[Route('/{id}/proofs/{proofId}/reject', name: 'api_admin_proof_reject', methods: ['POST'], requirements: ['id' => '\d+', 'proofId' => '\d+'])]
    public function rejectProof(#[CurrentUser] User $admin, int $id, int $proofId, Request $request): JsonResponse
    {
        return $this->reviewProof($admin, $id, $proofId, $request, accept: false);
    }

    #[Route('/{id}/proofs/{proofId}/file', name: 'api_admin_proof_file', methods: ['GET'], requirements: ['id' => '\d+', 'proofId' => '\d+'])]
    public function proofFile(int $id, int $proofId): Response
    {
        $found = $this->findProof($id, $proofId);
        if ($found instanceof JsonResponse) {
            return $found;
        }
        [, $proof] = $found;

        if (!$this->storage->exists($proof->getStoragePath())) {
            return $this->fail('proof_not_found', 'El archivo del comprobante no está en el almacén.', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($this->storage->absolutePath($proof->getStoragePath()));
        $response->headers->set('Content-Type', $proof->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition('inline', 'comprobante-' . $proof->getId() . '.' . pathinfo($proof->getStoragePath(), PATHINFO_EXTENSION));
        $response->setPrivate();

        return $response;
    }

    #[Route('/{id}/confirm', name: 'api_admin_bookings_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function confirm(int $id): JsonResponse
    {
        return $this->run($id, fn (Booking $b) => $this->workflow->confirm($b));
    }

    #[Route('/{id}/complete', name: 'api_admin_bookings_complete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function complete(int $id): JsonResponse
    {
        return $this->run($id, fn (Booking $b) => $this->workflow->complete($b));
    }

    #[Route('/{id}/no-show', name: 'api_admin_bookings_no_show', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function noShow(int $id): JsonResponse
    {
        return $this->run($id, fn (Booking $b) => $this->workflow->markNoShow($b));
    }

    #[Route('/{id}/reject', name: 'api_admin_bookings_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        $note = $this->readNote($request);

        return $this->run($id, fn (Booking $b) => $this->workflow->reject($b, $note));
    }

    #[Route('/{id}/cancel', name: 'api_admin_bookings_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        $note = $this->readNote($request);

        return $this->run($id, fn (Booking $b) => $this->workflow->cancelByAdmin($b, $note));
    }

    private function reviewProof(User $admin, int $id, int $proofId, Request $request, bool $accept): JsonResponse
    {
        $found = $this->findProof($id, $proofId);
        if ($found instanceof JsonResponse) {
            return $found;
        }
        [$booking, $proof] = $found;

        $payload = $this->readPayload($request);
        $note = $this->stringOrNull($payload['note'] ?? null);

        if (!$accept) {
            if (null === $note) {
                // El cliente verá el motivo: rechazar sin decir por qué no ayuda a nadie.
                return $this->fail('note_required', 'Indica por qué se rechaza, el cliente lo verá.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            try {
                $this->workflow->rejectProof($proof, $admin, $note);
            } catch (BookingException $e) {
                return $this->fail($e->errorCode, $e->getMessage(), $e->statusCode);
            }

            return new JsonResponse(['data' => $this->presenter->booking($booking, forAdmin: true)]);
        }

        // Al aceptar, el administrador registra lo que realmente entró: el
        // cliente ya no lo teclea, y con pagos partidos cada foto lleva el suyo.
        $rawAmount = trim((string) ($payload['declaredAmount'] ?? ''));
        if ('' === $rawAmount) {
            return $this->fail('amount_required', 'Indica el importe de esta transferencia.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (1 !== preg_match('/^\d{1,8}([.,]\d{1,2})?$/', $rawAmount)) {
            return $this->fail('invalid_amount', 'El importe no tiene un formato válido.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $amount = number_format((float) str_replace(',', '.', $rawAmount), 2, '.', '');

        try {
            $this->workflow->acceptProof(
                $proof,
                $admin,
                $note,
                $amount,
                $this->stringOrNull($payload['transferReference'] ?? null, 80),
            );
        } catch (BookingException $e) {
            return $this->fail($e->errorCode, $e->getMessage(), $e->statusCode);
        }

        return new JsonResponse(['data' => $this->presenter->booking($booking, forAdmin: true)]);
    }

    /** @return JsonResponse|array{Booking, PaymentProof} */
    private function findProof(int $bookingId, int $proofId): JsonResponse|array
    {
        $booking = $this->bookings->find($bookingId);
        if (null === $booking) {
            return $this->notFound();
        }

        foreach ($booking->getProofs() as $proof) {
            if ($proof->getId() === $proofId) {
                return [$booking, $proof];
            }
        }

        return $this->fail('proof_not_found', 'Esa reserva no tiene ese comprobante.', Response::HTTP_NOT_FOUND);
    }

    /** @param callable(Booking): void $action */
    private function run(int $id, callable $action): JsonResponse
    {
        $booking = $this->bookings->find($id);
        if (null === $booking) {
            return $this->notFound();
        }

        try {
            $action($booking);
        } catch (BookingException $e) {
            return $this->fail($e->errorCode, $e->getMessage(), $e->statusCode);
        }

        return new JsonResponse(['data' => $this->presenter->booking($booking, forAdmin: true)]);
    }

    private function readNote(Request $request): ?string
    {
        return $this->stringOrNull($this->readPayload($request)['note'] ?? null);
    }

    /** @return array<string, mixed> */
    private function readPayload(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function stringOrNull(mixed $value, ?int $maxLength = null): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ('' === $text) {
            return null;
        }

        return null === $maxLength ? $text : substr($text, 0, $maxLength);
    }

    private function notFound(): JsonResponse
    {
        return $this->fail('booking_not_found', 'No existe esa reserva.', Response::HTTP_NOT_FOUND);
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
