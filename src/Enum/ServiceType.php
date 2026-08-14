<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Se persiste como VARCHAR (no como ENUM nativo de MySQL) para poder añadir
 * casos nuevos sin ALTER TABLE.
 */
enum ServiceType: string
{
    /** Servicio suelto, se vende por sí mismo. */
    case Standalone = 'standalone';

    /** Paquete promocional con varios elementos incluidos. */
    case Promotion = 'promotion';

    public function label(): string
    {
        return match ($this) {
            self::Standalone => 'Servicio',
            self::Promotion => 'Promoción',
        };
    }
}
