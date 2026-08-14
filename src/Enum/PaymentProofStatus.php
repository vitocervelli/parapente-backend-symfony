<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentProofStatus: string
{
    /** Subido por el cliente, esperando revisión. */
    case Pending = 'pending';

    case Accepted = 'accepted';

    /** Foto ilegible, importe que no cuadra... El cliente puede subir otro. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En revisión',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
        };
    }
}
