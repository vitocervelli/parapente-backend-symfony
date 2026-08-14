<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un extra de pago que un asistente contrató, congelado al reservar: si mañana
 * cambia el precio o el nombre del extra en el catálogo, lo contratado no cambia.
 */
#[ORM\Entity]
#[ORM\Table(name: 'booking_attendee_extra')]
#[ORM\Index(name: 'idx_attendee_extra_attendee', columns: ['attendee_id'])]
class BookingAttendeeExtra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BookingAttendee::class, inversedBy: 'extras')]
    #[ORM\JoinColumn(name: 'attendee_id', nullable: false, onDelete: 'CASCADE')]
    private ?BookingAttendee $attendee = null;

    #[ORM\ManyToOne(targetEntity: Extra::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Extra $extra = null;

    // ── Congelados al reservar ──────────────────────────────────────────────

    #[ORM\Column(name: 'extra_name', length: 160)]
    private string $extraName = '';

    #[ORM\Column(name: 'price_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $priceAmount = '0.00';

    #[ORM\Column(length: 3, enumType: Currency::class)]
    private Currency $currency = Currency::Eur;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttendee(): ?BookingAttendee
    {
        return $this->attendee;
    }

    public function setAttendee(?BookingAttendee $attendee): static
    {
        $this->attendee = $attendee;

        return $this;
    }

    public function getExtra(): ?Extra
    {
        return $this->extra;
    }

    public function setExtra(?Extra $extra): static
    {
        $this->extra = $extra;

        return $this;
    }

    public function getExtraName(): string
    {
        return $this->extraName;
    }

    public function setExtraName(string $extraName): static
    {
        $this->extraName = $extraName;

        return $this;
    }

    public function getPriceAmount(): string
    {
        return $this->priceAmount;
    }

    public function setPriceAmount(string $priceAmount): static
    {
        $this->priceAmount = $priceAmount;

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
}
