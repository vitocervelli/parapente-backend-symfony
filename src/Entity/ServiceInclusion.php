<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une un servicio con un elemento del catálogo. Guarda lo que es propio de esa
 * pareja: el orden y, si hace falta, un texto a medida ("Torta de 1/2",
 * "Ramo de rosas mediano") sobre el mismo icono del catálogo.
 *
 * Sin índice único en (service_id, item_id): es legítimo repetir un concepto
 * con dos textos distintos, y en MySQL los NULL no colisionan entre sí, así que
 * incluir label_override en el índice no protegería de nada.
 */
#[ORM\Entity]
#[ORM\Table(name: 'service_inclusion')]
#[ORM\Index(name: 'idx_inclusion_service_pos', columns: ['service_id', 'position'])]
class ServiceInclusion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'inclusions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: InclusionItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Elige un elemento del catálogo.')]
    private ?InclusionItem $item = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $labelOverride = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getItem(): ?InclusionItem
    {
        return $this->item;
    }

    public function setItem(?InclusionItem $item): static
    {
        $this->item = $item;

        return $this;
    }

    public function getLabelOverride(): ?string
    {
        return $this->labelOverride;
    }

    public function setLabelOverride(?string $labelOverride): static
    {
        $this->labelOverride = $labelOverride;

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

    /** Texto a mostrar: el personalizado si existe, si no el del catálogo. */
    public function getLabel(): string
    {
        if (null !== $this->labelOverride && '' !== $this->labelOverride) {
            return $this->labelOverride;
        }

        return $this->item?->getDefaultLabel() ?? '';
    }

    public function getIcon(): string
    {
        return $this->item?->getIcon() ?? 'check';
    }

    public function getIconPath(): ?string
    {
        return $this->item?->getIconPath();
    }
}
