<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoEvidencia: string
{
    case Foto = 'foto';
    case Firma = 'firma';
    case Fachada = 'fachada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Foto => 'Foto de la entrega',
            self::Firma => 'Firma de quien recibe',
            self::Fachada => 'Foto de la fachada',
        };
    }
}
