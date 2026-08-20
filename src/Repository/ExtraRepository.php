<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Extra;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Extra>
 */
class ExtraRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Extra::class);
    }

    /** @return Extra[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.position', 'ASC')
            ->addOrderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
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

        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', array_keys($positionsById))
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $row->setPosition($positionsById[$row->getId()]);
        }

        return count($rows);
    }
}
