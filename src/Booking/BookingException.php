<?php

declare(strict_types=1);

namespace App\Booking;

/**
 * Fallo de negocio al reservar. Lleva el código y el estado HTTP para que el
 * controlador se limite a traducirlo, sin tener que interpretar mensajes.
 */
final class BookingException extends \RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self('invalid_booking', $message, 422);
    }

    /** @param array<string, mixed> $context */
    public static function slotFull(string $message, array $context = []): self
    {
        return new self('slot_full', $message, 409, $context);
    }
}
