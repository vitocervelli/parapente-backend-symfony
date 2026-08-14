<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Location;

/** Serializa las localidades (zonas de vuelo) para la web y el panel. */
final class LocationPresenter
{
    /** @return array<string, mixed> */
    public function location(Location $location): array
    {
        return [
            'id' => $location->getId(),
            'slug' => $location->getSlug(),
            'name' => $location->getName(),
            'region' => $location->getRegion(),
            'badge' => $location->getBadge(),
            'description' => $location->getDescription(),
            'position' => $location->getPosition(),
            'isActive' => $location->isActive(),
        ];
    }

    /**
     * @param iterable<Location> $locations
     *
     * @return list<array<string, mixed>>
     */
    public function locations(iterable $locations): array
    {
        $out = [];
        foreach ($locations as $location) {
            $out[] = $this->location($location);
        }

        return $out;
    }
}
