<?php

declare(strict_types=1);

namespace App\Services\Cargas;

/**
 * Datos del contacto real del comercio, tomados del texto de OBSERVACIONES.
 */
final readonly class ContactoExtraido
{
    public function __construct(
        public ?string $nombre = null,
        public ?string $telefono = null,
        public ?string $horario = null,
        public ?string $ciudad = null,
    ) {}

    public function estaVacio(): bool
    {
        return $this->nombre === null && $this->telefono === null && $this->horario === null;
    }
}
