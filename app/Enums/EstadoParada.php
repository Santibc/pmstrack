<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoParada: string
{
    case Pendiente = 'pendiente';
    case Entregada = 'entregada';
    case NoEntregada = 'no_entregada';
    case Anulada = 'anulada';
    /** La guía salió de esta ruta porque se reasignó a otro repartidor (sus rollos se fueron con ella). */
    case Reasignada = 'reasignada';
    /** No se alcanzó a visitar: al cerrar la ruta la guía volvió a planeación y sus rollos a bodega. */
    case SinVisitar = 'sin_visitar';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Entregada => 'Entregada',
            self::NoEntregada => 'No entregada',
            self::Anulada => 'Anulada',
            self::Reasignada => 'Reasignada',
            self::SinVisitar => 'Sin visitar',
        };
    }

    public function fueVisitada(): bool
    {
        return in_array($this, [self::Entregada, self::NoEntregada, self::Anulada], true);
    }

    /**
     * Estados que cuentan como parada activa de la ruta.
     *
     * @return list<self>
     */
    public static function activas(): array
    {
        return [self::Pendiente, self::Entregada, self::NoEntregada, self::Anulada];
    }

    /**
     * @return list<self>
     */
    public static function visitadas(): array
    {
        return [self::Entregada, self::NoEntregada, self::Anulada];
    }
}
