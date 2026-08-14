<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BookingSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingSettings>
 */
class BookingSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingSettings::class);
    }

    /**
     * Los ajustes son fila única. Si por lo que sea aún no existe (base recién
     * creada), se devuelve una instancia con los valores por defecto sin
     * persistirla: leer nunca debe fallar por falta de configuración.
     */
    public function get(): BookingSettings
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() ?? new BookingSettings();
    }
}
