<?php

declare(strict_types=1);

namespace App\Command;

use App\DataSeed\CatalogData;
use App\Entity\AvailabilitySlot;
use App\Entity\InclusionItem;
use App\Entity\Location;
use App\Entity\Service;
use App\Entity\ServiceInclusion;
use App\Enum\Currency;
use App\Enum\ServiceType;
use App\Repository\AvailabilitySlotRepository;
use App\Repository\InclusionItemRepository;
use App\Repository\LocationRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Carga el catálogo de partida. Es idempotente: identifica por slug y no toca
 * lo que ya existe salvo que se pase --force, así que no puede pisar lo que el
 * cliente haya editado desde el panel.
 */
#[AsCommand(
    name: 'app:seed:catalog',
    description: 'Carga los servicios y el catálogo de elementos incluidos.',
)]
final class SeedCatalogCommand extends \Symfony\Component\Console\Command\Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServiceRepository $services,
        private readonly InclusionItemRepository $items,
        private readonly LocationRepository $locations,
        private readonly AvailabilitySlotRepository $slots,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Sobrescribe los registros existentes y reconstruye sus inclusiones.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        // 1. Catálogo de elementos.
        /** @var array<string, InclusionItem> $itemsBySlug */
        $itemsBySlug = [];
        foreach (CatalogData::items() as $position => $row) {
            $item = $this->items->findOneBy(['slug' => $row['slug']]);

            if (null === $item) {
                $item = (new InclusionItem())->setSlug($row['slug']);
                $this->em->persist($item);
                ++$created;
            } elseif ($force) {
                ++$updated;
            } else {
                $itemsBySlug[$row['slug']] = $item;
                ++$skipped;
                continue;
            }

            $item->setDefaultLabel($row['label'])
                ->setIcon($row['icon'])
                ->setPosition($position);

            $itemsBySlug[$row['slug']] = $item;
        }

        $this->em->flush();

        // 1b. Zonas de vuelo (idempotente por slug).
        /** @var array<string, Location> $locationsBySlug */
        $locationsBySlug = [];
        foreach (CatalogData::locations() as $row) {
            $location = $this->locations->findOneBy(['slug' => $row['slug']]);

            if (null === $location) {
                $location = (new Location())->setSlug($row['slug']);
                $this->em->persist($location);
                ++$created;
            } elseif (!$force) {
                $locationsBySlug[$row['slug']] = $location;
                ++$skipped;
                continue;
            } else {
                ++$updated;
            }

            $location->setName($row['name'])
                ->setRegion($row['region'])
                ->setBadge($row['badge'])
                ->setDescription($row['description'])
                ->setPosition($row['position'])
                ->setIsActive(true);

            $locationsBySlug[$row['slug']] = $location;
        }

        $this->em->flush();

        // 2. Servicios y sus inclusiones.
        foreach (CatalogData::services() as $row) {
            $service = $this->services->findOneBy(['slug' => $row['slug']]);

            if (null !== $service && !$force) {
                ++$skipped;
                continue;
            }

            if (null === $service) {
                $service = (new Service())->setSlug($row['slug']);
                $this->em->persist($service);
                ++$created;
            } else {
                $service->touch();
                ++$updated;
            }

            $service
                ->setName($row['name'])
                ->setType(ServiceType::from($row['type']))
                ->setTagline($row['tagline'])
                ->setDescription($row['description'])
                ->setPriceAmount($row['price'])
                ->setCurrency(Currency::from($row['currency']))
                ->setPeople($row['people'])
                ->setPriceNote($row['priceNote'])
                ->setDurationMinutes($row['durationMinutes'])
                ->setBadge($row['badge'])
                ->setCoverImage($row['coverImage'])
                ->setPosition($row['position'])
                ->setIsActive(true);

            // Localidades donde se ofrece (por defecto Nirgua si el seed no lo dice).
            $service->clearLocations();
            foreach ($row['locations'] ?? ['nirgua'] as $locationSlug) {
                $location = $locationsBySlug[$locationSlug] ?? null;
                if (null === $location) {
                    $io->error(sprintf('La localidad "%s" no existe.', $locationSlug));

                    return self::FAILURE;
                }
                $service->addLocation($location);
            }

            // orphanRemoval limpia las filas sobrantes.
            $service->clearInclusions();

            foreach ($row['inclusions'] as $position => $inclusionRow) {
                $item = $itemsBySlug[$inclusionRow['item']] ?? null;
                if (null === $item) {
                    $io->error(sprintf('El elemento "%s" no existe en el catálogo.', $inclusionRow['item']));

                    return self::FAILURE;
                }

                $inclusion = (new ServiceInclusion())
                    ->setItem($item)
                    ->setLabelOverride($inclusionRow['label'] ?? null)
                    ->setPosition($position);

                $service->addInclusion($inclusion);
            }
        }

        $this->em->flush();

        // 3. Disponibilidad de prueba para las zonas nuevas, para poder ver la
        //    reserva funcionando. Solo si la zona aún no tiene ninguna franja.
        $slotsCreated = 0;
        $today = new \DateTimeImmutable('today');
        $horarios = [
            ['09:00', '10:00'],
            ['11:00', '12:00'],
        ];
        foreach (['la-guaira', 'merida'] as $slug) {
            $location = $locationsBySlug[$slug] ?? null;
            if (null === $location) {
                continue;
            }

            $tiene = $this->slots->findBetween($location, $today, $today->modify('+60 days'));
            if ([] !== $tiene) {
                continue;
            }

            for ($d = 1; $d <= 21; ++$d) {
                $dia = $today->modify(sprintf('+%d days', $d));
                // Solo viernes, sábado y domingo (N: 5,6,7).
                if (!\in_array((int) $dia->format('N'), [5, 6, 7], true)) {
                    continue;
                }

                foreach ($horarios as [$ini, $fin]) {
                    $this->em->persist(
                        (new AvailabilitySlot())
                            ->setLocation($location)
                            ->setDate($dia)
                            ->setStartTime(new \DateTimeImmutable('1970-01-01 ' . $ini . ':00'))
                            ->setEndTime(new \DateTimeImmutable('1970-01-01 ' . $fin . ':00'))
                            ->setCapacity(8)
                            ->setIsOpen(true),
                    );
                    ++$slotsCreated;
                }
            }
        }

        if ($slotsCreated > 0) {
            $this->em->flush();
            $io->note(sprintf('Disponibilidad de prueba: %d franjas creadas en zonas nuevas.', $slotsCreated));
        }

        $io->success(sprintf('Catálogo cargado: %d creados, %d actualizados, %d sin cambios.', $created, $updated, $skipped));

        if (!$force && $skipped > 0) {
            $io->note('Usa --force para sobrescribir los registros existentes.');
        }

        return self::SUCCESS;
    }
}
