<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoParada;
use App\Enums\EstadoRuta;
use App\Enums\ResultadoVisita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CerrarRutaRequest;
use App\Http\Requests\Api\GuardarOrdenRequest;
use App\Http\Resources\RutaResource;
use App\Models\Ruta;
use App\Services\Parametros;
use App\Services\Rutas\RutaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RutaController extends Controller
{
    public function __construct(private readonly RutaService $rutas) {}

    /**
     * Guías del día agrupadas por zona, con el orden guardado.
     */
    public function hoy(Request $request): JsonResponse
    {
        $ruta = $this->rutas->delDia($request->user()->repartidor);

        return response()->json([
            'data' => $ruta ? new RutaResource(RutaResource::cargar($ruta)) : null,
            'servidor_at' => now()->toIso8601String(),
        ]);
    }

    public function orden(GuardarOrdenRequest $request, Ruta $ruta): RutaResource
    {
        $this->rutas->guardarOrden($ruta, $request->validated('paradas'));

        return new RutaResource(RutaResource::cargar($ruta->refresh()));
    }

    public function iniciar(Request $request, Ruta $ruta): RutaResource
    {
        Gate::authorize('operar', $ruta);

        $datos = $request->validate(['iniciada_at' => ['nullable', 'date']]);

        $this->rutas->iniciar($ruta, $this->momento($datos['iniciada_at'] ?? null));

        return new RutaResource(RutaResource::cargar($ruta->refresh()));
    }

    /**
     * Resumen para la pantalla de cierre: rollos que el repartidor debe devolver.
     */
    public function cierre(Ruta $ruta): JsonResponse
    {
        Gate::authorize('operar', $ruta);

        $porDevolver = $this->rutas->rollosPorDevolver($ruta);

        $entregados = (int) $ruta->visitas()->vigentes()->where('resultado', ResultadoVisita::Entregado)->sum('rollos_entregados');

        return response()->json(['data' => [
            'rollos_salida' => $ruta->rollos_salida,
            'rollos_entregados' => $entregados,
            'rollos_por_devolver' => $porDevolver['esperados'],
            'rollos_por_caja' => app(Parametros::class)->rollosPorCaja(),
            'detalle' => $porDevolver['detalle'],
            'pendientes' => $ruta->paradas()->where('estado', EstadoParada::Pendiente)->count(),
        ]]);
    }

    /**
     * Cierre de ruta y rollos devueltos (genera el movimiento de inventario).
     */
    public function cerrar(CerrarRutaRequest $request, Ruta $ruta): RutaResource
    {
        $this->rutas->cerrar(
            $ruta,
            (int) $request->validated('rollos_devueltos'),
            $request->validated('observacion'),
            $this->momento($request->validated('cerrada_at')),
        );

        return new RutaResource(RutaResource::cargar($ruta->refresh()));
    }

    /**
     * Historial de rutas del repartidor (pestaña Historial de la app).
     */
    public function historial(Request $request): JsonResponse
    {
        $rutas = Ruta::query()
            ->where('repartidor_id', $request->user()->repartidor->id)
            ->where('estado', EstadoRuta::Cerrada)
            ->withCount([
                'paradas',
                'paradas as entregadas_count' => fn ($q) => $q->where('estado', EstadoParada::Entregada),
                'paradas as no_entregadas_count' => fn ($q) => $q->where('estado', EstadoParada::NoEntregada),
                'paradas as anuladas_count' => fn ($q) => $q->where('estado', EstadoParada::Anulada),
            ])
            ->withSum(['visitas as rollos_entregados_sum' => fn ($q) => $q->whereNull('anulada_at')->where('resultado', ResultadoVisita::Entregado)], 'rollos_entregados')
            ->latest('fecha')
            ->simplePaginate(20);

        return response()->json([
            'data' => $rutas->getCollection()->map(fn (Ruta $ruta): array => [
                'id' => $ruta->id,
                'fecha' => $ruta->fecha->toDateString(),
                'iniciada_at' => $ruta->iniciada_at?->toIso8601String(),
                'cerrada_at' => $ruta->cerrada_at?->toIso8601String(),
                'guias' => $ruta->paradas_count,
                'entregadas' => $ruta->entregadas_count,
                'no_entregadas' => $ruta->no_entregadas_count,
                'anuladas' => $ruta->anuladas_count,
                'rollos_salida' => $ruta->rollos_salida,
                'rollos_entregados' => (int) $ruta->rollos_entregados_sum,
                'rollos_devueltos' => $ruta->rollos_devueltos_declarados,
                'cuadre_ok' => $ruta->cuadre_ok,
            ]),
            'siguiente_pagina' => $rutas->hasMorePages() ? $rutas->currentPage() + 1 : null,
        ]);
    }

    /**
     * Hora del celular (acciones hechas sin señal). Nunca en el futuro.
     */
    private function momento(?string $fecha): ?CarbonImmutable
    {
        if ($fecha === null) {
            return null;
        }

        $momento = CarbonImmutable::parse($fecha)->setTimezone(config('app.timezone'));

        return $momento->greaterThan(now()->addMinutes(5)) ? null : $momento;
    }
}
