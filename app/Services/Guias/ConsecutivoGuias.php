<?php

declare(strict_types=1);

namespace App\Services\Guias;

use App\Models\Guia;
use App\Models\Parametro;
use App\Services\Parametros;
use Carbon\CarbonInterface;

/**
 * Número de guía PMS con el formato actual de la operación: MES-D-0000
 * (por ejemplo OCT-D-2070). El consecutivo no se reinicia con el mes.
 */
final class ConsecutivoGuias
{
    private const array MESES = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    public function __construct(private readonly Parametros $parametros) {}

    /**
     * Debe llamarse dentro de una transacción: bloquea el consecutivo hasta el commit.
     */
    public function siguiente(CarbonInterface $fecha): string
    {
        $parametro = Parametro::query()->where('clave', 'guia_consecutivo')->lockForUpdate()->first();

        if ($parametro === null) {
            $this->parametros->guardar('guia_consecutivo', Parametros::DEFINICION['guia_consecutivo']['defecto']);
            $parametro = Parametro::query()->where('clave', 'guia_consecutivo')->lockForUpdate()->firstOrFail();
        }

        $numero = max(1, (int) $parametro->valor);

        do {
            $guia = sprintf('%s-D-%04d', self::MESES[$fecha->month], $numero);
            $numero++;
        } while (Guia::query()->where('numero_guia', $guia)->exists());

        $parametro->forceFill(['valor' => (string) $numero])->saveQuietly();
        $this->parametros->olvidar();

        return $guia;
    }
}
