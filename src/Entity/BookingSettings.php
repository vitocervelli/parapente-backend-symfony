<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use App\Repository\BookingSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ajustes globales de reserva (fila única). Hoy solo la política de acompañantes:
 * el importe por acompañante de pago y cuántos van gratis por pasajero entre
 * semana (los fines de semana no hay gratis).
 */
#[ORM\Entity(repositoryClass: BookingSettingsRepository::class)]
#[ORM\Table(name: 'booking_settings')]
class BookingSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'companion_fee_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $companionFeeAmount = '5.00';

    #[ORM\Column(name: 'companion_fee_currency', length: 3, enumType: Currency::class)]
    private Currency $companionFeeCurrency = Currency::Eur;

    /** Acompañantes gratis por pasajero entre semana. Fin de semana: siempre 0. */
    #[ORM\Column(name: 'weekday_free_per_flyer', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 1])]
    #[Assert\Range(min: 0, max: 20)]
    private int $weekdayFreePerFlyer = 1;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCompanionFeeCurrency(): Currency
    {
        return $this->companionFeeCurrency;
    }

    public function setCompanionFeeCurrency(Currency $companionFeeCurrency): static
    {
        $this->companionFeeCurrency = $companionFeeCurrency;

        return $this;
    }

    public function getWeekdayFreePerFlyer(): int
    {
        return $this->weekdayFreePerFlyer;
    }

    public function setWeekdayFreePerFlyer(int $weekdayFreePerFlyer): static
    {
        $this->weekdayFreePerFlyer = $weekdayFreePerFlyer;

        return $this;
    }
}
