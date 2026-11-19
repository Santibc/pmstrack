<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Teléfonos colombianos tal como llegan en el Excel de Credibanco:
 * "+57 3218430002", 601256545, "+57 (4) 3104534", "+57 1234567"…
 */
final class Telefono
{
    private const array RELLENOS = ['1234567', '0000000', '1111111', '9999999', '12345678', '1234567890', '0000000000'];

    /**
     * Solo dígitos y sin el indicativo del país.
     */
    public static function limpiar(?string $telefono): ?string
    {
        $digitos = Texto::digitos($telefono);

        if (strlen($digitos) > 10 && str_starts_with($digitos, '57')) {
            $digitos = substr($digitos, 2);
        }

        return $digitos === '' ? null : $digitos;
    }

    /**
     * Completo: celular de 10 dígitos (3xx), fijo de 10 dígitos (60x) o fijo local de 7 u 8.
     */
    public static function esCompleto(?string $telefono): bool
    {
        $digitos = self::limpiar($telefono);

        if ($digitos === null || in_array($digitos, self::RELLENOS, true)) {
            return false;
        }

        return match (strlen($digitos)) {
            10 => $digitos[0] === '3' || str_starts_with($digitos, '60'),
            7, 8 => true,
            default => false,
        };
    }

    public static function esCelular(?string $telefono): bool
    {
        $digitos = self::limpiar($telefono);

        return $digitos !== null && strlen($digitos) === 10 && $digitos[0] === '3';
    }

    /**
     * "3105550142" → "310 555 0142" · "6012565459" → "(601) 256 5459".
     */
    public static function formatear(?string $telefono): ?string
    {
        $digitos = self::limpiar($telefono);

        if ($digitos === null) {
            return null;
        }

        return match (true) {
            strlen($digitos) === 10 && $digitos[0] === '3' => substr($digitos, 0, 3).' '.substr($digitos, 3, 3).' '.substr($digitos, 6),
            strlen($digitos) === 10 => '('.substr($digitos, 0, 3).') '.substr($digitos, 3, 3).' '.substr($digitos, 6),
            strlen($digitos) === 7 => substr($digitos, 0, 3).' '.substr($digitos, 3),
            default => $digitos,
        };
    }
}
