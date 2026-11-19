<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatea duraciones en el estilo de las pantallas: "41 h 48 min", "41 h", "52 min".
 */
final class Duracion
{
    /**
     * "41 h 48 min" · "52 min" · "3 h".
     */
    public static function corta(?int $minutos): string
    {
        if ($minutos === null) {
            return '—';
        }

        $minutos = max(0, $minutos);
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return match (true) {
            $horas === 0 => "{$resto} min",
            $resto === 0 => "{$horas} h",
            default => "{$horas} h {$resto} min",
        };
    }

    /**
     * Solo horas completas: "41 h". Por debajo de una hora muestra los minutos.
     */
    public static function horas(?int $minutos): string
    {
        if ($minutos === null) {
            return '—';
        }

        $minutos = max(0, $minutos);

        return $minutos < 60 ? "{$minutos} min" : intdiv($minutos, 60).' h';
    }
}
