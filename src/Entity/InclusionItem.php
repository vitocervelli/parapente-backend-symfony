<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InclusionItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catálogo reutilizable de elementos que pueden incluirse en un servicio.
 * Se da de alta una vez con su icono y se reutiliza en todas las promociones.
 */
#[ORM\Entity(repositoryClass: InclusionItemRepository::class)]
#[ORM\Table(name: 'inclusion_item')]
#[UniqueEntity(fields: ['slug'], message: 'Ya existe un elemento con ese identificador.')]
class InclusionItem
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
    #[Assert\NotBlank]
    private string $defaultLabel = '';

    /** Clave de icono ("horse", "cake"...). El frontend la mapea a un SVG propio. */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    private string $icon = 'check';

    /** Escotilla para subir un icono propio cuando la clave no basta. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconPath = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

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

    public function getDefaultLabel(): string
    {
        return $this->defaultLabel;
    }

    public function setDefaultLabel(string $defaultLabel): static
    {
        $this->defaultLabel = $defaultLabel;

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

    public function getIconPath(): ?string
    {
        return $this->iconPath;
    }

    public function setIconPath(?string $iconPath): static
    {
        $this->iconPath = $iconPath;

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

    public function __toString(): string
    {
        return $this->defaultLabel;
    }
}
