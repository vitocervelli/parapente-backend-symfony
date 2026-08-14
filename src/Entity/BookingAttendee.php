<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Quién vuela. Nombre, cédula y correo son obligatorios; el resto está
 * preparado para completarse más adelante desde el área del cliente (el peso
 * importa de verdad en parapente: el rango operativo es 40–110 kg).
 */
#[ORM\Entity]
#[ORM\Table(name: 'booking_attendee')]
#[ORM\Index(name: 'idx_attendee_line', columns: ['line_id'])]
class BookingAttendee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BookingLine::class, inversedBy: 'attendees')]
    #[ORM\JoinColumn(name: 'line_id', nullable: false, onDelete: 'CASCADE')]
    private ?BookingLine $line = null;

    #[ORM\Column(name: 'full_name', length: 160)]
    #[Assert\NotBlank(message: 'Falta el nombre del asistente.')]
    private string $fullName = '';

    #[ORM\Column(name: 'id_number', length: 40)]
    #[Assert\NotBlank(message: 'Falta la cédula del asistente.')]
    private string $idNumber = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Falta el correo del asistente.')]
    #[Assert\Email(message: 'El correo del asistente no es válido.')]
    private string $email = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'birth_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(name: 'weight_kg', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    #[Assert\Range(
        min: 20,
        max: 200,
        notInRangeMessage: 'El peso debe estar entre {{ min }} y {{ max }} kg (o déjalo vacío).',
    )]
    private ?int $weightKg = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, BookingAttendeeExtra> */
    #[ORM\OneToMany(mappedBy: 'attendee', targetEntity: BookingAttendeeExtra::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $extras;

    public function __construct()
    {
        $this->extras = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLine(): ?BookingLine
    {
        return $this->line;
    }

    public function setLine(?BookingLine $line): static
    {
        $this->line = $line;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getIdNumber(): string
    {
        return $this->idNumber;
    }

    public function setIdNumber(string $idNumber): static
    {
        $this->idNumber = $idNumber;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getWeightKg(): ?int
    {
        return $this->weightKg;
    }

    public function setWeightKg(?int $weightKg): static
    {
        $this->weightKg = $weightKg;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /** @return Collection<int, BookingAttendeeExtra> */
    public function getExtras(): Collection
    {
        return $this->extras;
    }

    public function addExtra(BookingAttendeeExtra $extra): static
    {
        if (!$this->extras->contains($extra)) {
            $this->extras->add($extra);
            $extra->setAttendee($this);
        }

        return $this;
    }

    /** Suma de los extras del asistente. Con bcmath para no tocar float. */
    public function getExtrasTotal(): string
    {
        $total = '0.00';
        foreach ($this->extras as $extra) {
            $total = bcadd($total, $extra->getPriceAmount(), 2);
        }

        return $total;
    }
}
