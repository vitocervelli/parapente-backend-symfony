<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GalleryPhotoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Una foto de la galería pública (/galeria). Las destacadas salen grandes como
 * polaroids arriba; el resto desfila en la tira inferior.
 */
#[ORM\Entity(repositoryClass: GalleryPhotoRepository::class)]
#[ORM\Table(name: 'gallery_photo')]
#[ORM\Index(name: 'idx_gallery_active_pos', columns: ['is_active', 'position'])]
class GalleryPhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Ruta relativa (public/uploads/gallery); el host lo pone el frontend. */
    #[ORM\Column(name: 'image_path', length: 255)]
    #[Assert\NotBlank(message: 'La foto necesita una imagen.')]
    private string $imagePath = '';

    /** Texto alternativo / pie: "Vuelo tándem sobre el valle". */
    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Describe brevemente la foto.')]
    private string $alt = '';

    /** Destacada: polaroid grande arriba en vez de la tira. */
    #[ORM\Column(name: 'is_featured', options: ['default' => false])]
    private bool $isFeatured = false;

    /** En la tira, ocupa el hueco ancho (fotos apaisadas). */
    #[ORM\Column(name: 'is_wide', options: ['default' => false])]
    private bool $isWide = false;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function setAlt(string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function isWide(): bool
    {
        return $this->isWide;
    }

    public function setIsWide(bool $isWide): static
    {
        $this->isWide = $isWide;

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
