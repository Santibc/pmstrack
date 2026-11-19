<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalización de textos para comparar datos que llegan escritos de formas distintas.
 */
final class Texto
{
    /**
     * Mayúsculas, sin tildes y con espacios simples: "Bogotá  " → "BOGOTA".
     */
    public static function normalizar(?string $texto): string
    {
        return (string) Str::of(Str::ascii((string) $texto))->upper()->squish();
    }

    /**
     * Clave de comparación de una ciudad o departamento: sin tildes, puntuación ni "D. C.".
     * "BOGOTÁ", "Bogota D.C." y "BOGOTA, D. C." producen "BOGOTA".
     */
    public static function claveLugar(?string $texto): string
    {
        $clave = preg_replace('/[^A-Z0-9 ]+/', ' ', self::normalizar($texto)) ?? '';
        $clave = preg_replace('/\s+D\s*C$/', '', trim((string) preg_replace('/\s+/', ' ', $clave))) ?? '';

        return trim($clave);
    }

    /**
     * Solo los dígitos de un texto.
     */
    public static function digitos(?string $texto): string
    {
        return preg_replace('/\D+/', '', (string) $texto) ?? '';
    }

    /**
     * Nombre propio legible: "MARIA ELVIRA  porras" → "Maria Elvira Porras".
     */
    public static function nombrePropio(?string $texto): string
    {
        return (string) Str::of((string) $texto)->squish()->lower()->title();
    }
}
