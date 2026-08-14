<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un servicio reservado en una franja concreta.
 *
 * Los campos "congelados" (nombre, precio, plazas por unidad) se copian del
 * catálogo al reservar: si mañana sube el precio o se renombra el servicio,
 * lo que el cliente contrató no cambia.
 */
#[ORM\Entity]
#[ORM\Table(name: 'booking_line')]
#[ORM\Index(name: 'idx_line_slot', columns: ['slot_id'])]
class BookingLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: AvailabilitySlot::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?AvailabilitySlot $slot = null;

    /** Cuántas veces se reserva este servicio en esta franja. */
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 1])]
    private int $quantity = 1;

    /** Plazas que retiene esta línea: quantity × seatsPerUnit. */
    #[ORM\Column(name: 'seats_total', type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $seatsTotal = 0;

    // ── Congelados al reservar ──────────────────────────────────────────────

    #[ORM\Column(name: 'service_name', length: 160)]
    private string $serviceName = '';

    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $unitPrice = '0.00';

    #[ORM\Column(length: 3, enumType: Currency::class)]
    private Currency $currency = Currency::Eur;

    #[ORM\Column(name: 'seats_per_unit', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 1])]
    private int $seatsPerUnit = 1;

    #[ORM\Column(name: 'people_per_unit', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 1])]
    private int $peoplePerUnit = 1;

    /** Acompañantes que no vuelan declarados en esta línea. */
    #[ORM\Column(name: 'companion_count', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $companionCount = 0;

    /** Tarifa de acompañantes congelada: se calcula al reservar según la política y el día. */
    #[ORM\Column(name: 'companion_fee_amount', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $companionFeeAmount = '0.00';

    /** @var Collection<int, BookingAttendee> */
    #[ORM\OneToMany(mappedBy: 'line', targetEntity: BookingAttendee::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attendees;

    public function __construct()
    {
        $this->attendees = new ArrayCollection();
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

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getSlot(): ?AvailabilitySlot
    {
        return $this->slot;
    }

    public function setSlot(?AvailabilitySlot $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getSeatsTotal(): int
    {
        return $this->seatsTotal;
    }

    public function setSeatsTotal(int $seatsTotal): static
    {
        $this->seatsTotal = $seatsTotal;

        return $this;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function setServiceName(string $serviceName): static
    {
        $this->serviceName = $serviceName;

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

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

    public function getSeatsPerUnit(): int
    {
        return $this->seatsPerUnit;
    }

    public function setSeatsPerUnit(int $seatsPerUnit): static
    {
        $this->seatsPerUnit = $seatsPerUnit;

        return $this;
    }

    public function getPeoplePerUnit(): int
    {
        return $this->peoplePerUnit;
    }

    public function setPeoplePerUnit(int $peoplePerUnit): static
    {
        $this->peoplePerUnit = $peoplePerUnit;

        return $this;
    }

    public function getCompanionCount(): int
    {
        return $this->companionCount;
    }

    public function setCompanionCount(int $companionCount): static
    {
        $this->companionCount = $companionCount;

        return $this;
    }

    public function getCompanionFeeAmount(): string
    {
        return $this->companionFeeAmount;
    }

    public function setCompanionFeeAmount(string $companionFeeAmount): static
    {
        $this->companionFeeAmount = $companionFeeAmount;

        return $this;
    }

    /** Cuántos asistentes hay que declarar en esta línea. */
    public function getExpectedAttendees(): int
    {
        return $this->quantity * $this->peoplePerUnit;
    }

    /** Importe base de la línea (servicio × cantidad). Con bcmath para no tocar float. */
    public function getLineTotal(): string
    {
        return bcmul($this->unitPrice, (string) $this->quantity, 2);
    }

    /** Suma de los extras de todos los asistentes de la línea. */
    public function getExtrasTotal(): string
    {
        $total = '0.00';
        foreach ($this->attendees as $attendee) {
            $total = bcadd($total, $attendee->getExtrasTotal(), 2);
        }

        return $total;
    }

    /** Total de la línea con todo: servicio + extras + tarifa de acompañantes. */
    public function getGrandTotal(): string
    {
        return bcadd(bcadd($this->getLineTotal(), $this->getExtrasTotal(), 2), $this->companionFeeAmount, 2);
    }

    /** @return Collection<int, BookingAttendee> */
    public function getAttendees(): Collection
    {
        return $this->attendees;
    }

    public function addAttendee(BookingAttendee $attendee): static
    {
        if (!$this->attendees->contains($attendee)) {
            $this->attendees->add($attendee);
            $attendee->setLine($this);
        }

        return $this;
    }
}
