<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Movimientos del inventario de rollos (documento técnico 5.6).
 * Saldo = entradas − salidas + devoluciones ± ajustes.
 */
enum TipoMovimiento: string
{
    case Entrada = 'entrada';
    case SalidaRuta = 'salida_ruta';
    case Devolucion = 'devolucion';
    case Ajuste = 'ajuste';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::SalidaRuta => 'Salida a ruta',
            self::Devolucion => 'Devolución',
            self::Ajuste => 'Ajuste',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Entrada => 'green',
            self::SalidaRuta => 'blue',
            self::Devolucion => 'amber',
            self::Ajuste => 'gray',
        };
    }
}
