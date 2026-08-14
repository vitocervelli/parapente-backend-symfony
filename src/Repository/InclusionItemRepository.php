<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InclusionItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InclusionItem>
 */
class InclusionItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InclusionItem::class);
    }

    /** @return InclusionItem[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.position', 'ASC')
            ->addOrderBy('i.defaultLabel', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
