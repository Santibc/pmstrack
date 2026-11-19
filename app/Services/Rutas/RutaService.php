<?php

declare(strict_types=1);

namespace App\Services\Rutas;

use App\Enums\EstadoGuia;
use App\Enums\EstadoParada;
use App\Enums\EstadoRuta;
use App\Enums\ResultadoVisita;
use App\Enums\TipoEvento;
use App\Exceptions\OperacionNoPermitida;
use App\Models\OrdenMemoria;
use App\Models\Parada;
use App\Models\Repartidor;
use App\Models\Ruta;
use App\Services\Bitacora;
use App\Services\Inventario\InventarioService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La ruta del repartidor: orden de visita (con memoria por zona), inicio y
 * cierre con devolución de rollos (documento técnico 5.3 y 5.6).
 */
final class RutaService
{
    public function __construct(
        private readonly Bitacora $bitacora,
        private readonly InventarioService $inventario,
    ) {}

    public function delDia(Repartidor $repartidor, ?CarbonInterface $fecha = null): ?Ruta
    {
        return Ruta::query()
            ->where('repartidor_id', $repartidor->id)
            ->whereDate('fecha', $fecha ?? today())
            ->first();
    }

    /**
     * Guarda el orden de visita elegido por el repartidor y lo recuerda por zona.
     * Se puede cambiar después de iniciar; ese cambio también se recuerda.
     *
     * @param  list<int>  $paradaIds  Paradas pendientes en el orden deseado.
     */
    public function guardarOrden(Ruta $ruta, array $paradaIds): void
    {
        $this->exigirAbierta($ruta);

        DB::transaction(function () use ($ruta, $paradaIds): void {
            $pendientes = $ruta->paradas()->where('estado', EstadoParada::Pendiente)->with('guia:id,direccion_id')->get()->keyBy('id');
            $ids = array_values(array_unique(array_map('intval', $paradaIds)));

            if (array_diff($ids, $pendientes->keys()->all()) !== []) {
                throw new OperacionNoPermitida('El orden incluye paradas que no están pendientes en esta ruta. Actualiza la ruta e inténtalo de nuevo.');
            }

            // Las pendientes que la app no envió (llegaron después) quedan al final, en su orden actual.
            $faltantes = $pendientes->except($ids)->sortBy(fn (Parada $p): array => [$p->orden === null ? 1 : 0, $p->orden ?? 0, $p->id])->keys()->all();
            $orden = [...$ids, ...$faltantes];
            $base = (int) $ruta->paradas()->whereIn('estado', EstadoParada::visitadas())->count();

            foreach ($orden as $indice => $id) {
                $pendientes[$id]->update(['orden' => $base + $indice + 1, 'es_nueva' => false]);
            }

            $ruta->update(['orden_guardado_at' => now()]);

            $this->recordarOrden($ruta, collect($orden)->map(fn (int $id): Parada => $pendientes[$id]));
        });
    }

    public function iniciar(Ruta $ruta, ?CarbonInterface $momento = null): Ruta
    {
        if ($ruta->estaCerrada()) {
            throw new OperacionNoPermitida('Esta ruta ya fue cerrada.');
        }

        if ($ruta->estaEnCurso()) {
            return $ruta;
        }

        $momento ??= now();

        return DB::transaction(function () use ($ruta, $momento): Ruta {
            $ruta->update(['estado' => EstadoRuta::EnCurso, 'iniciada_at' => $momento]);

            $paradas = $ruta->paradas()->where('estado', EstadoParada::Pendiente)->enOrden()->with('guia')->get();
            $base = (int) $ruta->paradas()->whereIn('estado', EstadoParada::visitadas())->count();
            $total = $base + $paradas->count();

            foreach ($paradas as $indice => $parada) {
                $posicion = $base + $indice + 1;

                // Al salir, todas las paradas tienen un lugar en la ruta (las nuevas van primero).
                $parada->update(['orden' => $posicion]);
                $parada->guia->update(['estado' => EstadoGuia::EnReparto]);

                $this->bitacora->registrar(
                    $parada->guia, TipoEvento::RutaIniciada, "Ruta iniciada · parada {$posicion} de {$total}",
                    ocurridoAt: $momento,
                );
            }

            return $ruta;
        });
    }

    /**
     * Rollos que el repartidor debería devolver: los de las visitas sin entrega,
     * las diferencias de las entregas parciales y las paradas que no alcanzó a visitar.
     *
     * @return array{esperados: int, detalle: list<array{guia: string, comercio: string, rollos: int, motivo: string}>}
     */
    public function rollosPorDevolver(Ruta $ruta): array
    {
        // Incluye las que volvieron a planeación al cerrar: sus rollos regresan con el repartidor.
        $paradas = Parada::query()
            ->where('ruta_id', $ruta->id)
            ->whereIn('estado', [...EstadoParada::activas(), EstadoParada::SinVisitar])
            ->with(['guia', 'visita'])
            ->get();
        $detalle = [];

        foreach ($paradas as $parada) {
            $solicitados = $parada->guia->rollos_solicitados;

            [$rollos, $motivo] = match ($parada->estado) {
                EstadoParada::Entregada => [max(0, $solicitados - (int) ($parada->visita?->rollos_entregados ?? $solicitados)), 'recibió '.($parada->visita?->rollos_entregados ?? $solicitados).' de '.$solicitados],
                EstadoParada::NoEntregada => [$solicitados, 'no entregada'],
                EstadoParada::Anulada => [$solicitados, 'anulada'],
                default => [$solicitados, 'sin visitar'],
            };

            if ($rollos > 0) {
                $detalle[] = [
                    'guia' => $parada->guia->numero_guia,
                    'comercio' => $parada->guia->nombre_comercio,
                    'rollos' => $rollos,
                    'motivo' => $motivo,
                ];
            }
        }

        // El repartidor puede sacar cajas completas: lo que debe volver es lo que salió
        // de bodega menos lo entregado, aunque supere lo que pedían sus guías.
        $entregados = (int) $ruta->visitas()->whereNull('anulada_at')->where('resultado', ResultadoVisita::Entregado)->sum('rollos_entregados');
        $esperados = max(0, $ruta->rollos_salida - $entregados);
        $porGuias = array_sum(array_column($detalle, 'rollos'));

        if ($esperados > $porGuias) {
            $detalle[] = [
                'guia' => '—',
                'comercio' => 'Rollos de más que salieron de bodega',
                'rollos' => $esperados - $porGuias,
                'motivo' => 'sobrante del despacho',
            ];
        }

        return ['esperados' => $esperados, 'detalle' => $detalle];
    }

    /**
     * Cierra la ruta. El repartidor declara los rollos que devuelve; el sistema
     * los compara con lo que debería volver y deja constancia si no cuadran.
     */
    public function cerrar(Ruta $ruta, int $rollosDevueltos, ?string $observacion = null, ?CarbonInterface $momento = null, string $origen = 'app'): Ruta
    {
        if ($ruta->estaCerrada()) {
            return $ruta;
        }

        if ($rollosDevueltos < 0) {
            throw new OperacionNoPermitida('Los rollos devueltos no pueden ser negativos.');
        }

        $momento ??= now();

        return DB::transaction(function () use ($ruta, $rollosDevueltos, $observacion, $momento, $origen): Ruta {
            $esperados = $this->rollosPorDevolver($ruta)['esperados'];

            // Las paradas que no se alcanzaron a visitar vuelven a planeación, sin sumar intento.
            $sinVisitar = $ruta->paradas()->where('estado', EstadoParada::Pendiente)->with('guia')->get();

            foreach ($sinVisitar as $parada) {
                $parada->update(['estado' => EstadoParada::SinVisitar]);
                $parada->guia->update(['estado' => EstadoGuia::ListaParaAsignar, 'repartidor_id' => null, 'fecha_programada' => null]);
                $this->bitacora->registrar($parada->guia, TipoEvento::Reprogramada, 'No se alcanzó a visitar; vuelve a planeación', ocurridoAt: $momento);
            }

            $ruta->loadMissing('repartidor.user');
            $ruta->update([
                'estado' => EstadoRuta::Cerrada,
                'cerrada_at' => $momento,
                'iniciada_at' => $ruta->iniciada_at ?? $momento,
                'rollos_devueltos_declarados' => $rollosDevueltos,
                'rollos_devueltos_esperados' => $esperados,
                'cuadre_ok' => $rollosDevueltos === $esperados,
                'observacion_cierre' => $observacion,
            ]);

            if ($rollosDevueltos > 0) {
                $sinEntrega = $ruta->paradas()->whereIn('estado', [EstadoParada::NoEntregada, EstadoParada::Anulada])->count();

                $this->inventario->devolucion(
                    $ruta,
                    $rollosDevueltos,
                    $ruta->repartidor->nombre.' · cierre de ruta, '.$sinEntrega.' sin entrega',
                    $origen,
                );
            }

            if ($rollosDevueltos !== $esperados) {
                activity('inventario')->performedOn($ruta)->causedBy($ruta->repartidor->user)
                    ->withProperties(['declarados' => $rollosDevueltos, 'esperados' => $esperados])
                    ->log("El cierre de ruta de {$ruta->repartidor->nombre} no cuadra: devolvió {$rollosDevueltos} y se esperaban {$esperados}");
            }

            return $ruta;
        });
    }

    /**
     * Actualiza la memoria de orden del repartidor, zona por zona, respetando
     * el lugar de las direcciones que hoy no están en la ruta.
     *
     * @param  Collection<int, Parada>  $enOrden
     */
    private function recordarOrden(Ruta $ruta, Collection $enOrden): void
    {
        foreach ($enOrden->groupBy('zona_id') as $zonaId => $paradas) {
            if ($zonaId === '' || $zonaId === null) {
                continue;
            }

            $hoy = $paradas->pluck('guia.direccion_id')->unique()->values()->all();

            $memoria = OrdenMemoria::query()
                ->where('repartidor_id', $ruta->repartidor_id)
                ->where('zona_id', $zonaId)
                ->orderBy('posicion')->orderBy('id')
                ->pluck('direccion_id')
                ->all();

            foreach (self::fusionar($memoria, $hoy) as $indice => $direccionId) {
                OrdenMemoria::query()->updateOrCreate(
                    ['repartidor_id' => $ruta->repartidor_id, 'zona_id' => $zonaId, 'direccion_id' => $direccionId],
                    ['posicion' => $indice + 1],
                );
            }
        }
    }

    /**
     * Mezcla el orden recordado con el de hoy.
     *
     * Las direcciones ya conocidas ocupan los mismos puestos que tenían, pero en
     * el orden de hoy; cada dirección nueva entra justo después de la que la
     * precede hoy. Ejemplo: memoria [A,B,C,D,E] y hoy [C,X,A] → [C,X,B,A,D,E].
     *
     * @param  list<int>  $memoria
     * @param  list<int>  $hoy
     * @return list<int>
     */
    public static function fusionar(array $memoria, array $hoy): array
    {
        $conocidasHoy = array_values(array_intersect($hoy, $memoria));
        $resultado = [];
        $siguiente = 0;

        foreach ($memoria as $direccion) {
            $resultado[] = in_array($direccion, $conocidasHoy, true) ? $conocidasHoy[$siguiente++] : $direccion;
        }

        $anterior = null;

        foreach ($hoy as $direccion) {
            if (! in_array($direccion, $resultado, true)) {
                $posicion = $anterior === null ? 0 : ((int) array_search($anterior, $resultado, true)) + 1;
                array_splice($resultado, $posicion, 0, [$direccion]);
            }

            $anterior = $direccion;
        }

        return $resultado;
    }

    private function exigirAbierta(Ruta $ruta): void
    {
        if ($ruta->estaCerrada()) {
            throw new OperacionNoPermitida('Esta ruta ya fue cerrada.');
        }
    }
}
