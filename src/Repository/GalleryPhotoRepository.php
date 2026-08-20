<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GalleryPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GalleryPhoto>
 */
class GalleryPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryPhoto::class);
    }

    /** @return GalleryPhoto[] */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** @return GalleryPhoto[] */
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

        $rows = $this->createQueryBuilder('g')
            ->andWhere('g.id IN (:ids)')
            ->setParameter('ids', array_keys($positionsById))
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $row->setPosition($positionsById[$row->getId()]);
        }

        return count($rows);
    }
}
