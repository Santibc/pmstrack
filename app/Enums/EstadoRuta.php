<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoRuta: string
{
    case Asignada = 'asignada';
    case EnCurso = 'en_curso';
    case Cerrada = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Asignada => 'Sin iniciar',
            self::EnCurso => 'En reparto',
            self::Cerrada => 'Ruta cerrada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Asignada => 'gray',
            self::EnCurso => 'blue',
            self::Cerrada => 'green',
        };
    }
}
