<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AllyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un aliado o patrocinador de la sección «Vuelan con nosotros» de la portada.
 * Si tiene logo se muestra la imagen; si no, el nombre con el estilo de rótulo.
 */
#[ORM\Entity(repositoryClass: AllyRepository::class)]
#[ORM\Table(name: 'ally')]
#[ORM\Index(name: 'idx_ally_active_pos', columns: ['is_active', 'position'])]
class Ally
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'El aliado necesita un nombre.')]
    private string $name = '';

    /** Qué es: "Panadería", "Tienda de regalos"… Sale bajo el nombre o el logo. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $kind = null;

    /** Ruta relativa del logo (public/uploads/allies); el host lo pone el frontend. */
    #[ORM\Column(name: 'logo_path', length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(?string $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

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
}
