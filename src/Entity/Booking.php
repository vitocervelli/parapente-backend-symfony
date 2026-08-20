<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BookingStatus;
use App\Enum\Currency;
use App\Enum\PaymentProofStatus;
use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'booking')]
#[ORM\UniqueConstraint(name: 'uniq_booking_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_booking_status_created', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_booking_customer', columns: ['customer_id', 'created_at'])]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Código legible que el cliente cita por WhatsApp. */
    #[ORM\Column(length: 24, unique: true)]
    private string $reference = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?User $customer = null;

    #[ORM\Column(length: 30, enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::PendingPayment;

    #[ORM\Column(name: 'contact_phone', length: 40, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(name: 'customer_note', type: Types::TEXT, nullable: true)]
    private ?string $customerNote = null;

    #[ORM\Column(name: 'admin_note', type: Types::TEXT, nullable: true)]
    private ?string $adminNote = null;

    /** Suma congelada de las líneas. String, nunca float. */
    #[ORM\Column(name: 'total_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(length: 3, enumType: Currency::class)]
    private Currency $currency = Currency::Eur;

    /** Cuándo deja de retener plazas si no se paga. */
    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'confirmed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    /**
     * Guardia de idempotencia: en cuanto se devuelven las plazas queda sellado,
     * y ninguna transición posterior puede devolverlas otra vez. Sin esto, un
     * doble clic en cancelar inflaría el cupo en silencio.
     */
    #[ORM\Column(name: 'seats_released_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $seatsReleasedAt = null;

    /**
     * Reserva histórica: alta manual de una reserva anterior al sistema. No
     * retiene plazas, nace en un estado final y sus líneas pueden no tener
     * franja. Sirve para filtrarlas en el panel y excluirlas del área de cliente.
     */
    #[ORM\Column(name: 'is_historical', options: ['default' => false])]
    private bool $isHistorical = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, BookingLine> */
    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: BookingLine::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $lines;

    /** @var Collection<int, PaymentProof> */
    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: PaymentProof::class, cascade: ['persist'])]
    #[ORM\OrderBy(['uploadedAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $proofs;

    /**
     * Galería del vuelo (fotos y vídeos que sube el equipo). A diferencia de
     * los comprobantes sí hay orphanRemoval: el admin puede borrar elementos,
     * y quien lo hace debe borrar también el fichero del almacén privado
     * (ver AdminBookingMediaController).
     *
     * @var Collection<int, BookingMedia>
     */
    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: BookingMedia::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['uploadedAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $media;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
        $this->proofs = new ArrayCollection();
        $this->media = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(?User $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    /** Solo desde BookingWorkflow: nadie más cambia el estado a mano. */
    public function setStatus(BookingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isHistorical(): bool
    {
        return $this->isHistorical;
    }

    public function setIsHistorical(bool $isHistorical): static
    {
        $this->isHistorical = $isHistorical;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;

        return $this;
    }

    public function getCustomerNote(): ?string
    {
        return $this->customerNote;
    }

    public function setCustomerNote(?string $customerNote): static
    {
        $this->customerNote = $customerNote;

        return $this;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): static
    {
        $this->adminNote = $adminNote;

        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    public function getSeatsReleasedAt(): ?\DateTimeImmutable
    {
        return $this->seatsReleasedAt;
    }

    public function markSeatsReleased(\DateTimeImmutable $when): static
    {
        $this->seatsReleasedAt = $when;

        return $this;
    }

    public function areSeatsReleased(): bool
    {
        return null !== $this->seatsReleasedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return Collection<int, BookingLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(BookingLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setBooking($this);
        }

        return $this;
    }

    /** Total de plazas que retiene esta reserva. */
    public function getTotalSeats(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->getSeatsTotal();
        }

        return $total;
    }

    /**
     * Personas reales de la reserva: cada unidad de un paquete de X personas
     * cuenta como X. No es lo mismo que las plazas (un servicio podría ocupar
     * más de una plaza por persona).
     */
    public function getTotalPeople(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->getExpectedAttendees();
        }

        return $total;
    }

    /** @return Collection<int, PaymentProof> */
    public function getProofs(): Collection
    {
        return $this->proofs;
    }

    public function addProof(PaymentProof $proof): static
    {
        if (!$this->proofs->contains($proof)) {
            $this->proofs->add($proof);
            $proof->setBooking($this);
        }

        return $this;
    }

    /** @return Collection<int, BookingMedia> */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(BookingMedia $media): static
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setBooking($this);
        }

        return $this;
    }

    public function removeMedia(BookingMedia $media): static
    {
        $this->media->removeElement($media);

        return $this;
    }

    public function hasPendingProof(): bool
    {
        foreach ($this->proofs as $proof) {
            if ($proof->isPending()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Suma de los comprobantes aceptados. Con bcmath: son importes de dinero y
     * el pago puede venir partido en varias transferencias.
     */
    public function getAcceptedAmount(): string
    {
        $total = '0.00';

        foreach ($this->proofs as $proof) {
            if (PaymentProofStatus::Accepted === $proof->getStatus() && null !== $proof->getDeclaredAmount()) {
                $total = bcadd($total, $proof->getDeclaredAmount(), 2);
            }
        }

        return $total;
    }

    /** Lo que queda por cobrar. Nunca negativo: si pagó de más, es 0. */
    public function getOutstandingAmount(): string
    {
        $pendiente = bcsub($this->totalAmount, $this->getAcceptedAmount(), 2);

        return 1 === bccomp($pendiente, '0.00', 2) ? $pendiente : '0.00';
    }

    public function isFullyPaid(): bool
    {
        return 0 === bccomp($this->getOutstandingAmount(), '0.00', 2);
    }

    /**
     * ¿Retiene plazas que ya debería haber soltado? Mismo criterio que
     * BookingRepository::findExpirable: viva, sin plazas liberadas, y con el
     * plazo de pago vencido o la fecha de vuelo ya pasada.
     */
    public function isOverdue(\DateTimeImmutable $now, \DateTimeImmutable $today): bool
    {
        if ($this->areSeatsReleased() || !$this->status->isLive()) {
            return false;
        }

        if (BookingStatus::PendingPayment === $this->status
            && null !== $this->expiresAt
            && $this->expiresAt < $now
        ) {
            return true;
        }

        foreach ($this->lines as $line) {
            $slot = $line->getSlot();
            if (null !== $slot && $slot->getDate() < $today) {
                return true;
            }
        }

        return false;
    }

    /** @return list<BookingAttendee> */
    public function getAllAttendees(): array
    {
        $out = [];
        foreach ($this->lines as $line) {
            foreach ($line->getAttendees() as $attendee) {
                $out[] = $attendee;
            }
        }

        return $out;
    }
}
