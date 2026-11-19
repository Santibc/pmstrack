<?php

declare(strict_types=1);

namespace App\Services\Zonificacion;

use App\Enums\EstadoGuia;
use App\Enums\TipoEvento;
use App\Exceptions\OperacionNoPermitida;
use App\Models\Comercio;
use App\Models\Direccion;
use App\Models\Guia;
use App\Models\User;
use App\Models\Zona;
use App\Services\Bitacora;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Memoria de zonas (documento técnico 5.2).
 *
 * Orden de búsqueda: 1) código único del comercio ya zonificado;
 * 2) dirección normalizada en la misma ciudad; 3) queda "por zonificar".
 */
final class ZonificacionService
{
    public const string ORIGEN_COMERCIO = 'comercio';

    public const string ORIGEN_DIRECCION = 'direccion';

    public function __construct(private readonly Bitacora $bitacora) {}

    /**
     * @return array{zona: Zona, origen: string}|null
     */
    public function resolver(?string $codigoUnico, ?int $ciudadId, string $claveDireccion, int $seccionalId): ?array
    {
        if ($codigoUnico !== null) {
            $zona = Comercio::query()->where('codigo_unico', $codigoUnico)->first()?->zona;

            if ($this->sirve($zona, $seccionalId)) {
                return ['zona' => $zona, 'origen' => self::ORIGEN_COMERCIO];
            }
        }

        if ($ciudadId !== null) {
            $zona = Direccion::query()
                ->where('ciudad_id', $ciudadId)
                ->where('clave_normalizada', $claveDireccion)
                ->first()?->zona;

            if ($this->sirve($zona, $seccionalId)) {
                return ['zona' => $zona, 'origen' => self::ORIGEN_DIRECCION];
            }
        }

        return null;
    }

    /**
     * La administradora elige (o corrige) la zona de una dirección. La elección
     * queda en el directorio y se aplica a las guías que la estaban esperando.
     *
     * @return int Guías que quedaron listas para asignar con este cambio.
     */
    public function asignarZona(Direccion $direccion, Zona $zona, User $usuario): int
    {
        $direccion->loadMissing('ciudad');

        if ($zona->seccional_id !== $direccion->ciudad->seccional_id) {
            throw new OperacionNoPermitida('La zona elegida pertenece a otra seccional.');
        }

        return DB::transaction(function () use ($direccion, $zona, $usuario): int {
            $direccion->forceFill([
                'zona_id' => $zona->id,
                'zonificada_por' => $usuario->id,
                'zonificada_at' => now(),
            ])->save();

            $guias = Guia::query()
                ->where('direccion_id', $direccion->id)
                ->whereIn('estado', [EstadoGuia::Recibida, EstadoGuia::PorZonificar, EstadoGuia::ListaParaAsignar])
                ->get();

            foreach ($guias as $guia) {
                $eraNueva = $guia->zona_id === null;

                if (! $eraNueva && $guia->zona_id === $zona->id) {
                    continue;
                }

                $guia->forceFill(['zona_id' => $zona->id, 'estado' => EstadoGuia::ListaParaAsignar])->save();

                $this->bitacora->registrar(
                    $guia,
                    TipoEvento::ZonaManual,
                    ($eraNueva ? 'Zona asignada: ' : 'Zona corregida: ').$zona->nombre_completo,
                    'elegida por '.$usuario->name,
                    $usuario,
                );
            }

            // El comercio recuerda su zona: primer criterio en la próxima carga.
            Comercio::query()->whereIn('id', $guias->pluck('comercio_id')->unique())->update(['zona_id' => $zona->id]);

            return $guias->count();
        });
    }

    /**
     * @param  list<int>  $direccionIds
     * @return int Direcciones zonificadas.
     */
    public function asignarMasivo(array $direccionIds, Zona $zona, User $usuario): int
    {
        $direcciones = Direccion::query()->with('ciudad')->whereKey($direccionIds)->get();

        foreach ($direcciones as $direccion) {
            $this->asignarZona($direccion, $zona, $usuario);
        }

        return $direcciones->count();
    }

    /**
     * Direcciones nuevas de la seccional que esperan zona (con guías abiertas).
     *
     * @return Builder<Direccion>
     */
    public function pendientes(int $seccionalId): Builder
    {
        return Direccion::query()
            ->sinZona()
            ->deSeccional($seccionalId)
            ->whereHas('guias', fn (Builder $q) => $q->where('estado', EstadoGuia::PorZonificar));
    }

    public function totalPendientes(?int $seccionalId = null): int
    {
        return Guia::query()
            ->where('estado', EstadoGuia::PorZonificar)
            ->when($seccionalId, fn (Builder $q, int $id) => $q->where('seccional_id', $id))
            ->distinct()
            ->count('direccion_id');
    }

    /**
     * Porcentaje de guías del periodo que recibieron su zona sin intervención.
     */
    public function porcentajeAutomatico(CarbonInterface $desde, CarbonInterface $hasta, ?int $seccionalId = null): ?int
    {
        $base = Guia::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($seccionalId, fn (Builder $q, int $id) => $q->where('seccional_id', $id));

        $total = (clone $base)->count();

        if ($total === 0) {
            return null;
        }

        $automaticas = (clone $base)
            ->whereHas('eventos', fn (Builder $q) => $q->where('tipo', TipoEvento::ZonaAutomatica))
            ->count();

        return (int) round($automaticas * 100 / $total);
    }

    private function sirve(?Zona $zona, int $seccionalId): bool
    {
        return $zona !== null && $zona->activo && $zona->seccional_id === $seccionalId;
    }
}
