<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Reel;

/** Serializa los reels para la portada y el panel. */
final class ReelPresenter
{
    /** @return array<string, mixed> */
    public function reel(Reel $reel): array
    {
        return [
            'id' => $reel->getId(),
            'videoPath' => $reel->getVideoPath(),
            'posterPath' => $reel->getPosterPath(),
            'caption' => $reel->getCaption(),
            'position' => $reel->getPosition(),
            'isActive' => $reel->isActive(),
        ];
    }

    /**
     * @param iterable<Reel> $reels
     *
     * @return list<array<string, mixed>>
     */
    public function reels(iterable $reels): array
    {
        $out = [];
        foreach ($reels as $reel) {
            $out[] = $this->reel($reel);
        }

        return $out;
    }
}
