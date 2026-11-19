<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoCausal: string
{
    case NoEntregado = 'no_entregado';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NoEntregado => 'No entregado',
            self::Anulado => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NoEntregado => 'amber',
            self::Anulado => 'red',
        };
    }
}
