<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Service;
use App\Enum\ServiceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Listado público. El JOIN FETCH evita el N+1 al serializar las inclusiones.
     *
     * @param bool $onlyHome limita a los marcados para el mosaico de la home
     *
     * @return Service[]
     */
    public function findPublished(?ServiceType $type = null, bool $onlyHome = false, ?string $locationSlug = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.inclusions', 'si')->addSelect('si')
            ->leftJoin('si.item', 'it')->addSelect('it')
            ->leftJoin('s.extras', 'se')->addSelect('se')
            ->leftJoin('se.extra', 'ex')->addSelect('ex')
            ->leftJoin('s.locations', 'loc')->addSelect('loc')
            ->andWhere('s.isActive = true')
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC');

        if (null !== $type) {
            $qb->andWhere('s.type = :type')->setParameter('type', $type);
        }

        if ($onlyHome) {
            $qb->andWhere('s.showOnHome = true');
        }

        if (null !== $locationSlug) {
            // Servicios ofrecidos en una zona activa concreta. El segundo alias
            // filtra sin recortar la colección `loc` ya cargada para el payload.
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\Location lf WHERE lf MEMBER OF s.locations AND lf.slug = :locSlug AND lf.isActive = true)')
                ->setParameter('locSlug', $locationSlug);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Aplica un nuevo orden en bloque.
     *
     * @param array<int, int> $positionsById id => posición
     *
     * @return int cuántos servicios se reordenaron
     */
    public function applyOrder(array $positionsById): int
    {
        if ([] === $positionsById) {
            return 0;
        }

        $services = $this->createQueryBuilder('s')
            ->andWhere('s.id IN (:ids)')
            ->setParameter('ids', array_keys($positionsById))
            ->getQuery()
            ->getResult();

        foreach ($services as $service) {
            $service->setPosition($positionsById[$service->getId()]);
        }

        return count($services);
    }

    public function findOnePublishedBySlug(string $slug): ?Service
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.inclusions', 'si')->addSelect('si')
            ->leftJoin('si.item', 'it')->addSelect('it')
            ->leftJoin('s.extras', 'se')->addSelect('se')
            ->leftJoin('se.extra', 'ex')->addSelect('ex')
            ->leftJoin('s.locations', 'loc')->addSelect('loc')
            ->andWhere('s.slug = :slug')
            ->andWhere('s.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Listado del panel: incluye también los inactivos.
     *
     * @return Service[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.inclusions', 'si')->addSelect('si')
            ->leftJoin('si.item', 'it')->addSelect('it')
            ->leftJoin('s.extras', 'se')->addSelect('se')
            ->leftJoin('se.extra', 'ex')->addSelect('ex')
            ->leftJoin('s.locations', 'loc')->addSelect('loc')
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneWithInclusions(int $id): ?Service
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.inclusions', 'si')->addSelect('si')
            ->leftJoin('si.item', 'it')->addSelect('it')
            ->leftJoin('s.extras', 'se')->addSelect('se')
            ->leftJoin('se.extra', 'ex')->addSelect('ex')
            ->leftJoin('s.locations', 'loc')->addSelect('loc')
            ->andWhere('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
