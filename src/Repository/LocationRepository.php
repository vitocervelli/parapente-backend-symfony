<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /** @return Location[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.position', 'ASC')
            ->addOrderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Location[] */
    public function findAllActiveOrdered(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isActive = true')
            ->orderBy('l.position', 'ASC')
            ->addOrderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneActiveBySlug(string $slug): ?Location
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.slug = :slug')
            ->andWhere('l.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Aplica el orden que dejó el arrastre del panel: posición = índice del id.
     *
     * @param array<int, int> $positionsById
     */
    public function applyOrder(array $positionsById): int
    {
        if ([] === $positionsById) {
            return 0;
        }

        $rows = $this->createQueryBuilder('l')
            ->andWhere('l.id IN (:ids)')
            ->setParameter('ids', array_keys($positionsById))
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $row->setPosition($positionsById[$row->getId()]);
        }

        return count($rows);
    }
}
