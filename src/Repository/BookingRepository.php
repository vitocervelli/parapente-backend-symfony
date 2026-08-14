<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\User;
use App\Enum\BookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Reservas de un cliente. El JOIN FETCH evita el N+1 al presentarlas.
     *
     * @return Booking[]
     */
    public function findForCustomer(User $customer): array
    {
        return $this->baseQuery()
            ->andWhere('b.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Una reserva concreta de un cliente. Filtrar por cliente aquí es la primera
     * capa que impide ver reservas ajenas cambiando la referencia en la URL.
     */
    public function findOneForCustomer(string $reference, User $customer): ?Booking
    {
        return $this->baseQuery()
            ->andWhere('b.reference = :reference')
            ->andWhere('b.customer = :customer')
            ->setParameter('reference', $reference)
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByReference(string $reference): ?Booking
    {
        return $this->baseQuery()
            ->andWhere('b.reference = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Bandeja del panel.
     *
     * @param BookingStatus[] $statuses
     *
     * @return Booking[]
     */
    public function findForAdmin(array $statuses = [], ?\DateTimeImmutable $from = null): array
    {
        $qb = $this->baseQuery()->orderBy('b.createdAt', 'DESC');

        if ([] !== $statuses) {
            $qb->andWhere('b.status IN (:statuses)')->setParameter('statuses', $statuses);
        }

        if (null !== $from) {
            $qb->andWhere('b.createdAt >= :from')->setParameter('from', $from);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Reservas que retienen plazas y ya deberían haberlas soltado.
     *
     * @return Booking[]
     */
    public function findExpirable(\DateTimeImmutable $now, \DateTimeImmutable $today): array
    {
        return $this->baseQuery()
            ->andWhere('b.seatsReleasedAt IS NULL')
            ->andWhere('b.status IN (:live)')
            ->andWhere('(b.status = :pending AND b.expiresAt IS NOT NULL AND b.expiresAt < :now) OR s.date < :today')
            ->setParameter('live', [BookingStatus::PendingPayment, BookingStatus::ProofSubmitted])
            ->setParameter('pending', BookingStatus::PendingPayment)
            ->setParameter('now', $now)
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
    }

    private function baseQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.lines', 'l')->addSelect('l')
            ->leftJoin('l.slot', 's')->addSelect('s')
            ->leftJoin('l.service', 'sv')->addSelect('sv')
            ->leftJoin('l.attendees', 'a')->addSelect('a')
            ->leftJoin('b.customer', 'c')->addSelect('c');
    }

    /**
     * Personas que han volado de verdad: suma de personas (unidades × personas
     * por paquete) de las reservas completadas. Un paquete de 2 cuenta como 2.
     */
    public function countPeopleFlown(): int
    {
        return (int) $this->getEntityManager()
            ->createQuery(
                'SELECT COALESCE(SUM(l.quantity * l.peoplePerUnit), 0)
                 FROM App\Entity\BookingLine l
                 JOIN l.booking b
                 WHERE b.status = :done',
            )
            ->setParameter('done', BookingStatus::Completed)
            ->getSingleScalarResult();
    }

    /** Siguiente número correlativo del año, para la referencia legible. */
    public function nextSequenceForYear(int $year): int
    {
        $prefix = sprintf('PBV-%d-', $year);

        $last = $this->createQueryBuilder('b')
            ->select('b.reference')
            ->andWhere('b.reference LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('b.reference', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $last) {
            return 1;
        }

        return 1 + (int) substr((string) $last['reference'], strlen($prefix));
    }
}
