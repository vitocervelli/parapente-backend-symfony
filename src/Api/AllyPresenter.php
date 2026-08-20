<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Ally;

/** Serializa los aliados para la portada y el panel. */
final class AllyPresenter
{
    /** @return array<string, mixed> */
    public function ally(Ally $ally): array
    {
        return [
            'id' => $ally->getId(),
            'name' => $ally->getName(),
            'kind' => $ally->getKind(),
            'logoPath' => $ally->getLogoPath(),
            'position' => $ally->getPosition(),
            'isActive' => $ally->isActive(),
        ];
    }

    /**
     * @param iterable<Ally> $allies
     *
     * @return list<array<string, mixed>>
     */
    public function allies(iterable $allies): array
    {
        $out = [];
        foreach ($allies as $ally) {
            $out[] = $this->ally($ally);
        }

        return $out;
    }
}
