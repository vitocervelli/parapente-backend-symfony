<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use App\Repository\ExtraRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catálogo reutilizable de extras de pago (p. ej. "paseo a caballo").
 * A diferencia de InclusionItem, lleva precio: se cobra por persona que lo elige.
 * Se da de alta una vez y se asigna a los servicios que lo ofrecen.
 */
#[ORM\Entity(repositoryClass: ExtraRepository::class)]
#[ORM\Table(name: 'extra')]
#[UniqueEntity(fields: ['slug'], message: 'Ya existe un extra con ese identificador.')]
class Extra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Usa minúsculas, números y guiones.')]
    private string $slug = '';

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'El nombre del extra es obligatorio.')]
    private string $name = '';

    /**
     * DECIMAL: Doctrine lo devuelve como string y así se serializa sin perder
     * precisión. Nunca convertir a float.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $priceAmount = '0.00';

    #[ORM\Column(length: 3, enumType: Currency::class)]
    private Currency $currency = Currency::Eur;

    /** Clave de icono ("horse", "cake"...). El frontend la mapea a un SVG propio. */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    private string $icon = 'check';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
