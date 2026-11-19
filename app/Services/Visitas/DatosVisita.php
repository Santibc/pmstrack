<?php

declare(strict_types=1);

namespace App\Services\Visitas;

use App\Enums\ResultadoVisita;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Lo que la app envía al registrar el resultado de una visita.
 */
final readonly class DatosVisita
{
    public function __construct(
        public string $uuid,
        public ResultadoVisita $resultado,
        public CarbonImmutable $registradaAt,
        public ?int $causalId = null,
        public ?string $observacion = null,
        public ?int $rollosEntregados = null,
        public ?string $recibeNombre = null,
        public ?string $recibeCedula = null,
        public ?string $recibeTelefono = null,
        public bool $autorizaDatos = false,
        public ?float $latitud = null,
        public ?float $longitud = null,
        public ?float $precision = null,
        public ?string $dispositivo = null,
        public ?string $appVersion = null,
        public ?UploadedFile $foto = null,
        public ?UploadedFile $firma = null,
        public ?UploadedFile $fachada = null,
    ) {}
}
