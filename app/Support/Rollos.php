<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Parametros;

/**
 * Los rollos llegan de Credibanco en cajas (360 por caja) y salen de bodega
 * por cajas completas o por rollos sueltos. El inventario se guarda siempre
 * en rollos; aquí se convierte para mostrarlo y registrarlo en cajas.
 */
final class Rollos
{
    public static function porCaja(): int
    {
        return app(Parametros::class)->rollosPorCaja();
    }

    /**
     * @return array{cajas: int, sueltos: int}
     */
    public static function desglose(int $rollos): array
    {
        $porCaja = self::porCaja();
        $absoluto = abs($rollos);

        return ['cajas' => intdiv($absoluto, $porCaja), 'sueltos' => $absoluto % $porCaja];
    }

    public static function total(int $cajas, int $sueltos): int
    {
        return $cajas * self::porCaja() + $sueltos;
    }

    /**
     * "13 cajas y 190 rollos" · "2 cajas" · "45 rollos".
     */
    public static function enCajas(int $rollos): string
    {
        ['cajas' => $cajas, 'sueltos' => $sueltos] = self::desglose($rollos);

        $partes = array_filter([
            $cajas > 0 ? $cajas.' '.($cajas === 1 ? 'caja' : 'cajas') : null,
            $sueltos > 0 || $cajas === 0 ? $sueltos.' '.($sueltos === 1 ? 'rollo' : 'rollos') : null,
        ]);

        return ($rollos < 0 ? '−' : '').implode(' y ', $partes);
    }

    /**
     * "4.870" con separador de miles.
     */
    public static function numero(int|float $valor): string
    {
        return number_format($valor, 0, ',', '.');
    }
}
