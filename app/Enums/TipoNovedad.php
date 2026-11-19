<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Incidentes que el repartidor reporta a la administradora desde la app.
 */
enum TipoNovedad: string
{
    case ViaCerrada = 'via_cerrada';
    case Accidente = 'accidente';
    case DireccionNoEncontrada = 'direccion_no_encontrada';
    case Vehiculo = 'vehiculo';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ViaCerrada => 'Vía cerrada',
            self::Accidente => 'Accidente',
            self::DireccionNoEncontrada => 'Dirección no encontrada',
            self::Vehiculo => 'Falla del vehículo',
            self::Otro => 'Otra novedad',
        };
    }
}
