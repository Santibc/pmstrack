<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida de una guía (documento técnico, Figura 2).
 */
enum EstadoGuia: string
{
    case Recibida = 'recibida';
    case PorZonificar = 'por_zonificar';
    case ListaParaAsignar = 'lista_para_asignar';
    case Asignada = 'asignada';
    case EnReparto = 'en_reparto';
    case Entregada = 'entregada';
    case NoEntregada = 'no_entregada';
    case Anulada = 'anulada';
    case DevueltaCredibanco = 'devuelta_credibanco';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Recibida => 'Recibida',
            self::PorZonificar => 'Por zonificar',
            self::ListaParaAsignar => 'Lista para asignar',
            self::Asignada => 'Asignada a ruta',
            self::EnReparto => 'En reparto',
            self::Entregada => 'Entregada',
            self::NoEntregada => 'No entregada',
            self::Anulada => 'Anulada',
            self::DevueltaCredibanco => 'Devuelta a Credibanco',
        };
    }

    /**
     * Etiqueta en masculino, como la ve Credibanco ("servicio entregado").
     */
    public function etiquetaServicio(): string
    {
        return match ($this) {
            self::Recibida, self::PorZonificar, self::ListaParaAsignar => 'Recibido',
            self::Asignada => 'Asignado a ruta',
            self::EnReparto => 'En reparto',
            self::Entregada => 'Entregado',
            self::NoEntregada => 'No entregado',
            self::Anulada => 'Anulado',
            self::DevueltaCredibanco => 'Devuelto a Credibanco',
        };
    }

    /**
     * Variante visual del componente <x-badge>.
     */
    public function color(): string
    {
        return match ($this) {
            self::Recibida, self::ListaParaAsignar => 'gray',
            self::PorZonificar => 'orange',
            self::Asignada => 'purple',
            self::EnReparto => 'blue',
            self::Entregada => 'green',
            self::NoEntregada => 'amber',
            self::Anulada => 'red',
            self::DevueltaCredibanco => 'dark',
        };
    }

    public function esFinal(): bool
    {
        return in_array($this, [self::Entregada, self::Anulada, self::DevueltaCredibanco], true);
    }

    /**
     * Estados en los que la guía todavía no ha salido a la calle.
     *
     * @return list<self>
     */
    public static function enBodega(): array
    {
        return [self::Recibida, self::PorZonificar, self::ListaParaAsignar];
    }

    /**
     * Estados en los que la guía sigue abierta (cuenta para el ANS).
     *
     * @return list<self>
     */
    public static function abiertos(): array
    {
        return [
            self::Recibida,
            self::PorZonificar,
            self::ListaParaAsignar,
            self::Asignada,
            self::EnReparto,
            self::NoEntregada,
        ];
    }
}
