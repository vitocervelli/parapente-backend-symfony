<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AvailabilitySlotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Una franja de vuelo con su cupo: "el 14 de septiembre, de 9:00 a 10:00, 15 plazas".
 *
 * La fecha y las horas se guardan como hora de pared (DATE + TIME), sin zona
 * horaria: las 9:00 en Nirgua son las 9:00 pase lo que pase con el reloj del
 * servidor. Para obtener un instante concreto está startsAtIn(), que es el
 * ÚNICO sitio que combina fecha y hora — Doctrine devuelve los TIME con fecha
 * 1970-01-01 y compararlos a pelo con "now" da resultados absurdos.
 */
#[ORM\Entity(repositoryClass: AvailabilitySlotRepository::class)]
#[ORM\Table(name: 'availability_slot')]
#[ORM\UniqueConstraint(name: 'uniq_slot_loc_date_start', columns: ['location_id', 'slot_date', 'start_time'])]
#[ORM\Index(name: 'idx_slot_loc_open_date', columns: ['location_id', 'is_open', 'slot_date'])]
#[Assert\Callback('validateTimes')]
class AvailabilitySlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** La zona de vuelo a la que pertenece esta franja. Cada zona tiene su calendario. */
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'La franja necesita una localidad.')]
    private ?Location $location = null;

    #[ORM\Column(name: 'slot_date', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $date;

    #[ORM\Column(name: 'start_time', type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(name: 'end_time', type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $endTime;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Range(min: 0, max: 500)]
    private int $capacity = 0;

    /**
     * Contador denormalizado de plazas ocupadas. Es el recurso que se bloquea al
     * reservar; calcularlo con un SUM() obligaría a bloquear todas las líneas de
     * reserva de la franja para lograr el mismo aislamiento.
     */
    #[ORM\Column(name: 'seats_booked', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $seatsBooked = 0;

    #[ORM\Column(name: 'is_open', options: ['default' => true])]
    private bool $isOpen = true;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->date = new \DateTimeImmutable('today');
        $this->startTime = new \DateTimeImmutable('1970-01-01 09:00:00');
        $this->endTime = new \DateTimeImmutable('1970-01-01 10:00:00');
    }

    public function validateTimes(ExecutionContextInterface $context): void
    {
        if ($this->endTime <= $this->startTime) {
            $context->buildViolation('La hora de fin debe ser posterior a la de inicio.')
                ->atPath('endTime')
                ->addViolation();
        }

        if ($this->seatsBooked > $this->capacity) {
            $context->buildViolation('Ya hay {{ booked }} plazas reservadas: el cupo no puede bajar de ahí.')
                ->setParameter('{{ booked }}', (string) $this->seatsBooked)
                ->atPath('capacity')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        // Sin hora: es una fecha, y arrastrar horas rompería el índice único.
        $this->date = $date->setTime(0, 0);

        return $this;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getSeatsBooked(): int
    {
        return $this->seatsBooked;
    }

    public function getSeatsFree(): int
    {
        return max(0, $this->capacity - $this->seatsBooked);
    }

    public function hasRoomFor(int $seats): bool
    {
        return $this->isOpen && $this->getSeatsFree() >= $seats;
    }

    /** Solo desde el servicio que sostiene el bloqueo de la fila. */
    public function reserveSeats(int $seats): static
    {
        $this->seatsBooked += $seats;

        return $this;
    }

    /**
     * Devuelve plazas al cupo. El max(0) evita que un contador desviado se
     * vuelva negativo, pero es una red de seguridad: si salta, hay un fallo de
     * contabilidad en otro sitio.
     */
    public function releaseSeats(int $seats): static
    {
        $this->seatsBooked = max(0, $this->seatsBooked - $seats);

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * El único punto del código que combina fecha y hora en un instante.
     * Nadie más debe hacerlo por su cuenta.
     */
    public function startsAtIn(\DateTimeZone $tz): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            $this->date->format('Y-m-d') . ' ' . $this->startTime->format('H:i:s'),
            $tz,
        );
    }

    public function hasPassed(\DateTimeImmutable $now, \DateTimeZone $tz): bool
    {
        return $this->startsAtIn($tz) < $now;
    }
}
