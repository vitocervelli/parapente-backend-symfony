<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MediaKind;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una foto o vídeo del vuelo, subido por el equipo a la reserva para que el
 * cliente lo vea (y descargue) desde su cuenta.
 *
 * Como los comprobantes, el fichero NO vive en public/: `storagePath` es
 * relativo al almacén privado (var/uploads) y solo se sirve por endpoints
 * autenticados. Las fotos llegan ya comprimidas desde el panel (el navegador
 * las reduce antes de subir); los vídeos se guardan tal cual con tope de
 * tamaño.
 */
#[ORM\Entity]
#[ORM\Table(name: 'booking_media')]
#[ORM\Index(name: 'idx_media_booking', columns: ['booking_id', 'uploaded_at'])]
class BookingMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\Column(length: 10, enumType: MediaKind::class)]
    private MediaKind $kind = MediaKind::Image;

    #[ORM\Column(name: 'storage_path', length: 255)]
    private string $storagePath = '';

    #[ORM\Column(name: 'original_name', length: 200)]
    private string $originalName = '';

    #[ORM\Column(name: 'mime_type', length: 60)]
    private string $mimeType = '';

    #[ORM\Column(name: 'size_bytes', options: ['unsigned' => true])]
    private int $sizeBytes = 0;

    /** SHA-256 del fichero: detecta la misma foto subida dos veces. */
    #[ORM\Column(length: 64)]
    private string $checksum = '';

    #[ORM\Column(name: 'uploaded_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $uploadedAt;

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

    public function getKind(): MediaKind
    {
        return $this->kind;
    }

    public function setKind(MediaKind $kind): static
    {
        $this->kind = $kind;

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

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
