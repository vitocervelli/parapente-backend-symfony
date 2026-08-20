<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un vídeo vertical de la sección «Vívelo en movimiento» (reels) de la portada.
 */
#[ORM\Entity(repositoryClass: ReelRepository::class)]
#[ORM\Table(name: 'reel')]
#[ORM\Index(name: 'idx_reel_active_pos', columns: ['is_active', 'position'])]
class Reel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Ruta relativa del vídeo (public/uploads/reels). */
    #[ORM\Column(name: 'video_path', length: 255)]
    #[Assert\NotBlank(message: 'El reel necesita un vídeo.')]
    private string $videoPath = '';

    /** Imagen de portada opcional; si es null se usa el primer fotograma. */
    #[ORM\Column(name: 'poster_path', length: 255, nullable: true)]
    private ?string $posterPath = null;

    /** Título breve opcional, solo para orientarse en el panel. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVideoPath(): string
    {
        return $this->videoPath;
    }

    public function setVideoPath(string $videoPath): static
    {
        $this->videoPath = $videoPath;

        return $this;
    }

    public function getPosterPath(): ?string
    {
        return $this->posterPath;
    }

    public function setPosterPath(?string $posterPath): static
    {
        $this->posterPath = $posterPath;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

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
