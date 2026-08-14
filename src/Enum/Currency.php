<?php

declare(strict_types=1);

namespace App\Enum;

enum Currency: string
{
    case Usd = 'USD';
    case Eur = 'EUR';

    public function symbol(): string
    {
        return match ($this) {
            self::Usd => '$',
            self::Eur => '€',
        };
    }

    /**
     * El importe llega como string desde Doctrine (DECIMAL). Se recorta la parte
     * decimal solo cuando no aporta nada: "180.00" => "180", "49.50" => "49,50".
     */
    public function format(string $amount): string
    {
        $trimmed = str_contains($amount, '.')
            ? rtrim(rtrim($amount, '0'), '.')
            : $amount;

        return match ($this) {
            self::Usd => '$' . $trimmed,
            self::Eur => str_replace('.', ',', $trimmed) . '€',
        };
    }
}
