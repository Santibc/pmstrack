<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Semáforo de ANS por guía (documento técnico 5.5).
 */
enum SemaforoAns: string
{
    case EnCurso = 'en_curso';
    case EnRiesgo = 'en_riesgo';
    case Vencido = 'vencido';
    case Cumplido = 'cumplido';
    case Incumplido = 'incumplido';
    case NoAplica = 'no_aplica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnCurso => 'En curso',
            self::EnRiesgo => 'En riesgo',
            self::Vencido, self::Incumplido => 'Vencido',
            self::Cumplido => 'Cumplido',
            self::NoAplica => 'No aplica',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EnCurso, self::NoAplica => 'gray',
            self::EnRiesgo => 'amber',
            self::Vencido, self::Incumplido => 'red',
            self::Cumplido => 'green',
        };
    }
}
