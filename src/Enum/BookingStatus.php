<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Estados de una reserva. Se persiste como VARCHAR.
 *
 * Distinción importante: rechazar un COMPROBANTE devuelve la reserva a
 * PendingPayment conservando las plazas (el cliente sube otra foto); rechazar
 * la RESERVA la mata y libera las plazas.
 */
enum BookingStatus: string
{
    /** Creada, esperando que el cliente pague y suba el comprobante. */
    case PendingPayment = 'pending_payment';

    /** Comprobante subido, pendiente de que el equipo lo revise. */
    case ProofSubmitted = 'proof_submitted';

    case Confirmed = 'confirmed';

    /** Caducó sin pagar. */
    case Expired = 'expired';

    case CancelledByCustomer = 'cancelled_by_customer';
    case CancelledByAdmin = 'cancelled_by_admin';

    /** El equipo la descarta (no llegó la transferencia, datos falsos...). */
    case Rejected = 'rejected';

    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::ProofSubmitted => 'Comprobante en revisión',
            self::Confirmed => 'Confirmada',
            self::Expired => 'Caducada',
            self::CancelledByCustomer => 'Cancelada por el cliente',
            self::CancelledByAdmin => 'Cancelada',
            self::Rejected => 'Rechazada',
            self::Completed => 'Completada',
            self::NoShow => 'No se presentó',
        };
    }

    /** Estados en los que la reserva sigue ocupando plazas. */
    public function holdsSeats(): bool
    {
        return match ($this) {
            self::PendingPayment, self::ProofSubmitted, self::Confirmed,
            self::Completed, self::NoShow => true,
            self::Expired, self::CancelledByCustomer, self::CancelledByAdmin,
            self::Rejected => false,
        };
    }

    /** Un estado final ya no admite transiciones. */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Expired, self::CancelledByCustomer, self::CancelledByAdmin,
            self::Rejected, self::Completed, self::NoShow => true,
            default => false,
        };
    }

    /** El cliente aún puede actuar sobre ella (subir comprobante, cancelar). */
    public function isLive(): bool
    {
        return self::PendingPayment === $this || self::ProofSubmitted === $this;
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::PendingPayment => \in_array($target, [
                self::ProofSubmitted, self::Confirmed, self::Expired,
                self::CancelledByCustomer, self::CancelledByAdmin, self::Rejected,
            ], true),
            self::ProofSubmitted => \in_array($target, [
                self::Confirmed, self::PendingPayment, self::Expired,
                self::CancelledByCustomer, self::CancelledByAdmin, self::Rejected,
            ], true),
            self::Confirmed => \in_array($target, [
                self::Completed, self::NoShow, self::CancelledByAdmin,
            ], true),
            default => false,
        };
    }
}
