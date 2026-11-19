<?php

declare(strict_types=1);

namespace App\Services\Direcciones;

/**
 * Resultado de normalizar una dirección.
 */
final readonly class DireccionNormalizada
{
    public function __construct(
        /** Dirección principal en formato estándar: "KR 7 # 61 - 48". */
        public string $principal,
        /** Local, piso, oficina, centro comercial… (no afecta la zona). */
        public ?string $complemento,
        /** Clave con la que se compara contra el directorio: "KR7#61-48". */
        public string $clave,
        /** Vía por kilómetro, vereda o similar: sin zona urbana definida. */
        public bool $esRural,
        /** Se reconoció la nomenclatura (tipo de vía + números). */
        public bool $estructurada,
    ) {}

    public function completa(): string
    {
        return trim($this->principal.' '.($this->complemento ?? ''));
    }
}
