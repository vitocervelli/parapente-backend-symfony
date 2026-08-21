<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\User;

/**
 * Resultado de resolver el cliente de una reserva del panel.
 *
 * `temporaryPassword` solo trae valor cuando se ha CREADO una cuenta nueva: es
 * la contraseña temporal en claro que hay que enviarle por correo. Si el cliente
 * ya existía, es null y no se toca ninguna credencial.
 */
final readonly class ProvisionedCustomer
{
    public function __construct(
        public User $user,
        public ?string $temporaryPassword = null,
    ) {
    }

    public function isNew(): bool
    {
        return null !== $this->temporaryPassword;
    }
}
