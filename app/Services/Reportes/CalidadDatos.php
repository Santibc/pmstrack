<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\Guia;
use App\Models\Visita;
use App\Services\Cargas\AnalizadorFilas;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comercios con dirección errada, cerrados o con teléfono incompleto, para
 * que Credibanco depure su base (documento técnico 4.2).
 *
 * Una guía entra aquí por lo que encontró el repartidor en terreno (causales
 * marcadas como "afecta la calidad de datos") o por lo que detectó la carga.
 */
final class CalidadDatos
{
    public const string TERRENO = 'terreno';

    public const string TELEFONO = 'telefono';

    public const string CIUDAD = 'ciudad';

    /**
     * @return Builder<Guia>
     */
    public function consulta(?CarbonInterface $desde = null, ?CarbonInterface $hasta = null, ?int $seccionalId = null, ?string $tipo = null): Builder
    {
        return Guia::query()
            ->when($desde && $hasta, fn (Builder $q) => $q->whereBetween('fecha_registro', [$desde, $hasta]))
            ->when($seccionalId, fn (Builder $q, int $id) => $q->where('seccional_id', $id))
            ->where(function (Builder $q) use ($tipo): void {
                if ($tipo === null || $tipo === self::TERRENO) {
                    $q->orWhereHas('visitas', $this->visitaConDatoErrado(...));
                }

                if ($tipo === null || $tipo === self::TELEFONO) {
                    $q->orWhere('alertas', 'like', '%"'.AnalizadorFilas::ALERTA_TELEFONO.'"%');
                }

                if ($tipo === null || $tipo === self::CIUDAD) {
                    $q->orWhere('alertas', 'like', '%"'.AnalizadorFilas::ALERTA_CIUDAD.'"%');
                }
            });
    }

    /**
     * Comercios distintos con algún dato por actualizar.
     */
    public function total(?CarbonInterface $desde = null, ?CarbonInterface $hasta = null, ?int $seccionalId = null): int
    {
        return $this->consulta($desde, $hasta, $seccionalId)->distinct()->count('comercio_id');
    }

    /**
     * Descripción de lo que hay que corregir en una guía.
     *
     * @return list<string>
     */
    public function hallazgos(Guia $guia): array
    {
        $hallazgos = [];

        foreach ($guia->visitas as $visita) {
            if (! $visita->estaAnulada() && $visita->causal?->afecta_calidad_datos) {
                $hallazgos[] = $visita->causal->nombre.($visita->observacion ? ': '.$visita->observacion : '');
            }
        }

        foreach ($guia->alertas ?? [] as $alerta) {
            if (in_array($alerta['codigo'], [AnalizadorFilas::ALERTA_TELEFONO, AnalizadorFilas::ALERTA_CIUDAD], true)) {
                $hallazgos[] = ucfirst($alerta['mensaje']);
            }
        }

        return $hallazgos;
    }

    /**
     * @param  Builder<Visita>  $q
     */
    private function visitaConDatoErrado(Builder $q): void
    {
        $q->whereNull('anulada_at')->whereHas('causal', fn (Builder $c) => $c->where('afecta_calidad_datos', true));
    }
}
