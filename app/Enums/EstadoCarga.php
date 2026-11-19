<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoCarga: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'En revisión',
            self::Confirmada => 'Confirmada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'amber',
            self::Confirmada => 'green',
            self::Cancelada => 'gray',
        };
    }
}
