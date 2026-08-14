<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentProofStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un comprobante de pago subido por el cliente.
 *
 * Una reserva puede acumular varios: si uno se rechaza (foto borrosa, importe
 * que no cuadra), el cliente sube otro y el historial completo queda a la
 * vista del equipo.
 *
 * El fichero NO vive en public/ — es un documento sensible. `storagePath` es
 * relativo al almacén privado (var/uploads) y solo se sirve por un endpoint
 * autenticado.
 */
#[ORM\Entity]
#[ORM\Table(name: 'payment_proof')]
#[ORM\Index(name: 'idx_proof_booking', columns: ['booking_id', 'uploaded_at'])]
#[ORM\Index(name: 'idx_proof_status', columns: ['status'])]
class PaymentProof
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'proofs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\Column(name: 'storage_path', length: 255)]
    private string $storagePath = '';

    #[ORM\Column(name: 'original_name', length: 200)]
    private string $originalName = '';

    #[ORM\Column(name: 'mime_type', length: 60)]
    private string $mimeType = '';

    #[ORM\Column(name: 'size_bytes', options: ['unsigned' => true])]
    private int $sizeBytes = 0;

    /** SHA-256 del fichero: detecta el mismo comprobante subido dos veces. */
    #[ORM\Column(length: 64)]
    private string $checksum = '';

    /** Lo que el cliente dice haber transferido. String, nunca float. */
    #[ORM\Column(name: 'declared_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $declaredAmount = null;

    /** Número de referencia de la transferencia, si lo aporta. */
    #[ORM\Column(name: 'transfer_reference', length: 80, nullable: true)]
    private ?string $transferReference = null;

    #[ORM\Column(length: 20, enumType: PaymentProofStatus::class)]
    private PaymentProofStatus $status = PaymentProofStatus::Pending;

    #[ORM\Column(name: 'uploaded_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column(name: 'reviewed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    /** Quién lo revisó. SET NULL: borrar la cuenta del admin no borra el historial. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewed_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    /** El porqué de un rechazo; el cliente lo ve. */
    #[ORM\Column(name: 'review_note', type: Types::TEXT, nullable: true)]
    private ?string $reviewNote = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): static
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function setChecksum(string $checksum): static
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function getDeclaredAmount(): ?string
    {
        return $this->declaredAmount;
    }

    public function setDeclaredAmount(?string $declaredAmount): static
    {
        $this->declaredAmount = $declaredAmount;

        return $this;
    }

    public function getTransferReference(): ?string
    {
        return $this->transferReference;
    }

    public function setTransferReference(?string $transferReference): static
    {
        $this->transferReference = $transferReference;

        return $this;
    }

    public function getStatus(): PaymentProofStatus
    {
        return $this->status;
    }

    /** Solo desde BookingWorkflow. */
    public function setStatus(PaymentProofStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function markReviewed(User $reviewer, ?string $note, \DateTimeImmutable $when): static
    {
        $this->reviewedBy = $reviewer;
        $this->reviewNote = $note;
        $this->reviewedAt = $when;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function getReviewNote(): ?string
    {
        return $this->reviewNote;
    }

    /** Solo desde BookingWorkflow: corregir la nota de un comprobante ya revisado. */
    public function setReviewNote(?string $reviewNote): static
    {
        $this->reviewNote = $reviewNote;

        return $this;
    }

    public function isPending(): bool
    {
        return PaymentProofStatus::Pending === $this->status;
    }
}
