<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Resultado de validar una fila del Excel de Credibanco.
 */
enum ResultadoFila: string
{
    case Nuevo = 'nuevo';
    case Duplicado = 'duplicado';
    case Error = 'error';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Nuevo => 'Servicio nuevo',
            self::Duplicado => 'Duplicado · se omite',
            self::Error => 'Con error · no se carga',
        };
    }
}
