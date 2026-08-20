<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ally;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ally>
 */
class AllyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ally::class);
    }

    /** @return Ally[] */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** @return Ally[] */
    public function findAllActiveOrdered(): array
    {
        return $this->findBy(['isActive' => true], ['position' => 'ASC', 'id' => 'ASC']);
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

        $allies = $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', array_keys($positionsById))
            ->getQuery()
            ->getResult();

        foreach ($allies as $ally) {
            $ally->setPosition($positionsById[$ally->getId()]);
        }

        return count($allies);
    }
}
