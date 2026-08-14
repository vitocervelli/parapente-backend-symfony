<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use App\Enum\ServiceType;
use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'service')]
#[ORM\Index(name: 'idx_service_type_active_pos', columns: ['type', 'is_active', 'position'])]
#[UniqueEntity(fields: ['slug'], message: 'Ya existe un servicio con ese identificador.')]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'El nombre es obligatorio.')]
    private string $name = '';

    /** 128 y no 255: el prefijo de índice de MySQL se queda corto con utf8mb4. */
    #[ORM\Column(length: 128, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Usa minúsculas, números y guiones.')]
    private string $slug = '';

    #[ORM\Column(length: 20, enumType: ServiceType::class)]
    private ServiceType $type = ServiceType::Promotion;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tagline = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

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

    /** Tamaño del paquete: hay promociones pensadas para dos personas. */
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 1])]
    #[Assert\Range(min: 1, max: 10)]
    private int $people = 1;

    /**
     * Plazas del cupo que consume una unidad de este servicio. Si es null se usa
     * $people, que es lo natural: un paquete de pareja ocupa dos plazas.
     *
     * Es un eje distinto de $people a propósito: "personas" dice cuántos
     * asistentes hay que pedir, "plazas" cuánto cupo se descuenta. Normalmente
     * coinciden, pero no tienen por qué (una promo de dos personas en la que
     * solo vuela una).
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    #[Assert\Range(min: 1, max: 20)]
    private ?int $seatsPerBooking = null;

    /** Si va vacío se deriva de $people. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $priceNote = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $durationMinutes = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $badge = null;

    /** Ruta relativa; el host lo pone el frontend. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $flyerImage = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /**
     * Si aparece en el mosaico de la home. Independiente de $isActive: un
     * servicio puede estar publicado en /servicios sin ocupar sitio en la
     * portada.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $showOnHome = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, ServiceInclusion> */
    #[ORM\OneToMany(mappedBy: 'service', targetEntity: ServiceInclusion::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $inclusions;

    /** @var Collection<int, ServiceExtra> */
    #[ORM\OneToMany(mappedBy: 'service', targetEntity: ServiceExtra::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $extras;

    /**
     * Localidades donde se ofrece el servicio. Un mismo servicio puede darse en
     * varias zonas (p. ej. el vuelo suelto en Nirgua y en Mérida).
     *
     * @var Collection<int, Location>
     */
    #[ORM\ManyToMany(targetEntity: Location::class, inversedBy: 'services')]
    #[ORM\JoinTable(name: 'service_location')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $locations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->inclusions = new ArrayCollection();
        $this->extras = new ArrayCollection();
        $this->locations = new ArrayCollection();
    }

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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getType(): ServiceType
    {
        return $this->type;
    }

    public function setType(ServiceType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTagline(): ?string
    {
        return $this->tagline;
    }

    public function setTagline(?string $tagline): static
    {
        $this->tagline = $tagline;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getPeople(): int
    {
        return $this->people;
    }

    public function setPeople(int $people): static
    {
        $this->people = $people;

        return $this;
    }

    public function getSeatsPerBooking(): ?int
    {
        return $this->seatsPerBooking;
    }

    public function setSeatsPerBooking(?int $seatsPerBooking): static
    {
        $this->seatsPerBooking = $seatsPerBooking;

        return $this;
    }

    /** Plazas efectivas: las explícitas o, si no hay, tantas como personas. */
    public function getResolvedSeatsPerBooking(): int
    {
        return $this->seatsPerBooking ?? $this->people;
    }

    public function getPriceNote(): ?string
    {
        return $this->priceNote;
    }

    public function setPriceNote(?string $priceNote): static
    {
        $this->priceNote = $priceNote;

        return $this;
    }

    /** Nota de precio efectiva: la explícita, o una derivada del nº de personas. */
    public function getResolvedPriceNote(): string
    {
        if (null !== $this->priceNote && '' !== $this->priceNote) {
            return $this->priceNote;
        }

        return 1 === $this->people ? 'por persona' : sprintf('para %d personas', $this->people);
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getFlyerImage(): ?string
    {
        return $this->flyerImage;
    }

    public function setFlyerImage(?string $flyerImage): static
    {
        $this->flyerImage = $flyerImage;

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

    public function isShownOnHome(): bool
    {
        return $this->showOnHome;
    }

    public function setShowOnHome(bool $showOnHome): static
    {
        $this->showOnHome = $showOnHome;

        return $this;
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

    /** @return Collection<int, ServiceInclusion> */
    public function getInclusions(): Collection
    {
        return $this->inclusions;
    }

    public function addInclusion(ServiceInclusion $inclusion): static
    {
        if (!$this->inclusions->contains($inclusion)) {
            $this->inclusions->add($inclusion);
            $inclusion->setService($this);
        }

        return $this;
    }

    public function removeInclusion(ServiceInclusion $inclusion): static
    {
        if ($this->inclusions->removeElement($inclusion) && $inclusion->getService() === $this) {
            // orphanRemoval borra la fila; soltar el lado propietario evita
            // que Doctrine intente un UPDATE con service_id NULL antes del DELETE.
            $inclusion->setService(null);
        }

        return $this;
    }

    public function clearInclusions(): static
    {
        foreach ($this->inclusions->toArray() as $inclusion) {
            $this->removeInclusion($inclusion);
        }

        return $this;
    }

    /** @return Collection<int, ServiceExtra> */
    public function getExtras(): Collection
    {
        return $this->extras;
    }

    public function addExtra(ServiceExtra $extra): static
    {
        if (!$this->extras->contains($extra)) {
            $this->extras->add($extra);
            $extra->setService($this);
        }

        return $this;
    }

    public function removeExtra(ServiceExtra $extra): static
    {
        if ($this->extras->removeElement($extra) && $extra->getService() === $this) {
            // Igual que en inclusiones: soltar el lado propietario evita el UPDATE
            // con service_id NULL antes del DELETE de orphanRemoval.
            $extra->setService(null);
        }

        return $this;
    }

    public function clearExtras(): static
    {
        foreach ($this->extras->toArray() as $extra) {
            $this->removeExtra($extra);
        }

        return $this;
    }

    /** @return Collection<int, Location> */
    public function getLocations(): Collection
    {
        return $this->locations;
    }

    public function addLocation(Location $location): static
    {
        if (!$this->locations->contains($location)) {
            $this->locations->add($location);
        }

        return $this;
    }

    public function removeLocation(Location $location): static
    {
        $this->locations->removeElement($location);

        return $this;
    }

    public function clearLocations(): static
    {
        $this->locations->clear();

        return $this;
    }

    public function hasLocation(Location $location): bool
    {
        return $this->locations->contains($location);
    }
}
