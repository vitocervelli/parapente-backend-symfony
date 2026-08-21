<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Contraseña temporal fácil de teclear: dos bloques cortos separados por guion
 * (p. ej. "krmn-3f8h"). El alfabeto evita caracteres que se confunden al leer
 * (0/O, 1/l/I), para que el cliente pueda copiarla de un correo sin errores.
 */
final class ReadablePasswordGenerator
{
    /** Sin 0, o, 1, l, i para no confundir al teclear. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function generate(int $blocks = 2, int $blockLength = 4): string
    {
        $parts = [];
        for ($b = 0; $b < $blocks; ++$b) {
            $chunk = '';
            for ($i = 0; $i < $blockLength; ++$i) {
                $chunk .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
            }
            $parts[] = $chunk;
        }

        return implode('-', $parts);
    }
}
