<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AvailabilitySlot;
use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AvailabilitySlot>
 */
class AvailabilitySlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvailabilitySlot::class);
    }

    /**
     * Franjas de una zona en un rango de fechas, ordenadas para pintar el calendario.
     *
     * @return AvailabilitySlot[]
     */
    public function findBetween(
        Location $location,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $onlyOpen = false,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.location = :location')
            ->andWhere('s.date >= :from')
            ->andWhere('s.date <= :to')
            ->setParameter('location', $location)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->orderBy('s.date', 'ASC')
            ->addOrderBy('s.startTime', 'ASC');

        if ($onlyOpen) {
            $qb->andWhere('s.isOpen = true');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return AvailabilitySlot[] */
    public function findByDate(Location $location, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.location = :location')
            ->andWhere('s.date = :date')
            ->setParameter('location', $location)
            ->setParameter('date', $date->setTime(0, 0))
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByDateAndStart(
        Location $location,
        \DateTimeImmutable $date,
        \DateTimeImmutable $startTime,
    ): ?AvailabilitySlot {
        return $this->createQueryBuilder('s')
            ->andWhere('s.location = :location')
            ->andWhere('s.date = :date')
            ->andWhere('s.startTime = :start')
            ->setParameter('location', $location)
            ->setParameter('date', $date->setTime(0, 0))
            ->setParameter('start', $startTime)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Fechas de una zona con al menos una franja abierta con sitio, para marcar
     * el calendario sin traerse todas las franjas.
     *
     * @return string[] fechas en formato Y-m-d
     */
    public function findDatesWithAvailability(
        Location $location,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $rows = $this->createQueryBuilder('s')
            ->select('DISTINCT s.date AS d')
            ->andWhere('s.location = :location')
            ->andWhere('s.date >= :from')
            ->andWhere('s.date <= :to')
            ->andWhere('s.isOpen = true')
            ->andWhere('s.capacity > s.seatsBooked')
            ->setParameter('location', $location)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->orderBy('d', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(
            static fn (array $row): string => (new \DateTimeImmutable((string) $row['d']))->format('Y-m-d'),
            $rows,
        );
    }
}
