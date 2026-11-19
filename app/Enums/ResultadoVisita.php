<?php

declare(strict_types=1);

namespace App\Enums;

enum ResultadoVisita: string
{
    case Entregado = 'entregado';
    case NoEntregado = 'no_entregado';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Entregado => 'Entregado',
            self::NoEntregado => 'No entregado',
            self::Anulado => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Entregado => 'green',
            self::NoEntregado => 'amber',
            self::Anulado => 'red',
        };
    }

    public function estadoParada(): EstadoParada
    {
        return match ($this) {
            self::Entregado => EstadoParada::Entregada,
            self::NoEntregado => EstadoParada::NoEntregada,
            self::Anulado => EstadoParada::Anulada,
        };
    }

    public function estadoGuia(): EstadoGuia
    {
        return match ($this) {
            self::Entregado => EstadoGuia::Entregada,
            self::NoEntregado => EstadoGuia::NoEntregada,
            self::Anulado => EstadoGuia::Anulada,
        };
    }

    public function tipoCausal(): ?TipoCausal
    {
        return match ($this) {
            self::Entregado => null,
            self::NoEntregado => TipoCausal::NoEntregado,
            self::Anulado => TipoCausal::Anulado,
        };
    }
}
