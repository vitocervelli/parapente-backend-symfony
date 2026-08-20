<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\GalleryPhoto;

/** Serializa las fotos de la galería para la web y el panel. */
final class GalleryPresenter
{
    /** @return array<string, mixed> */
    public function photo(GalleryPhoto $photo): array
    {
        return [
            'id' => $photo->getId(),
            'imagePath' => $photo->getImagePath(),
            'alt' => $photo->getAlt(),
            'isFeatured' => $photo->isFeatured(),
            'isWide' => $photo->isWide(),
            'position' => $photo->getPosition(),
            'isActive' => $photo->isActive(),
        ];
    }

    /**
     * @param iterable<GalleryPhoto> $photos
     *
     * @return list<array<string, mixed>>
     */
    public function photos(iterable $photos): array
    {
        $out = [];
        foreach ($photos as $photo) {
            $out[] = $this->photo($photo);
        }

        return $out;
    }
}
