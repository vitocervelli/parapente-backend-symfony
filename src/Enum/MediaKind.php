<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Tipo de un elemento de la galería de una reserva. Se deriva del MIME una
 * sola vez al subir (la whitelist del controlador es la fuente de verdad) y
 * queda persistido para que presenter y frontend no repitan la lógica.
 */
enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Foto',
            self::Video => 'Vídeo',
        };
    }
}
