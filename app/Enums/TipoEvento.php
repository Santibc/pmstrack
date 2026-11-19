<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de evento de la bitácora de trazabilidad de una guía.
 */
enum TipoEvento: string
{
    case SolicitudRegistrada = 'solicitud_registrada';
    case Cargada = 'cargada';
    case ZonaAutomatica = 'zona_automatica';
    case ZonaManual = 'zona_manual';
    case Asignada = 'asignada';
    case Reasignada = 'reasignada';
    case RutaIniciada = 'ruta_iniciada';
    case Entregada = 'entregada';
    case NoEntregada = 'no_entregada';
    case Anulada = 'anulada';
    case Reprogramada = 'reprogramada';
    case VisitaAnulada = 'visita_anulada';
    case DevueltaCredibanco = 'devuelta_credibanco';
    case Actualizada = 'actualizada';

    /**
     * Color del punto en la línea de tiempo.
     */
    public function color(): string
    {
        return match ($this) {
            self::SolicitudRegistrada, self::Cargada, self::Actualizada => 'gray',
            self::ZonaAutomatica, self::ZonaManual, self::Asignada, self::Reasignada => 'purple',
            self::RutaIniciada => 'blue',
            self::Entregada => 'green',
            self::NoEntregada, self::Reprogramada => 'amber',
            self::Anulada, self::VisitaAnulada, self::DevueltaCredibanco => 'red',
        };
    }
}
