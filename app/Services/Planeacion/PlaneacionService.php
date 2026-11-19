<?php

declare(strict_types=1);

namespace App\Services\Planeacion;

use App\Enums\EstadoGuia;
use App\Enums\EstadoParada;
use App\Enums\EstadoRuta;
use App\Enums\TipoEvento;
use App\Exceptions\OperacionNoPermitida;
use App\Models\Guia;
use App\Models\OrdenMemoria;
use App\Models\Parada;
use App\Models\Planeacion;
use App\Models\PlaneacionZona;
use App\Models\Repartidor;
use App\Models\Ruta;
use App\Models\Seccional;
use App\Models\User;
use App\Models\Zona;
use App\Services\Bitacora;
use App\Services\Inventario\InventarioService;
use App\Services\Notificaciones\PushService;
use App\Services\Parametros;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Planeación del día (documento técnico 4.1): la administradora asigna cada
 * zona a un repartidor y publica. Al publicar se crean las rutas, se notifica
 * a los repartidores y los rollos salen del inventario.
 */
final class PlaneacionService
{
    public function __construct(
        private readonly Parametros $parametros,
        private readonly Bitacora $bitacora,
        private readonly InventarioService $inventario,
        private readonly PushService $push,
    ) {}

    public function obtener(Seccional $seccional, CarbonInterface $fecha): Planeacion
    {
        // Se busca por día (no por igualdad exacta) para que funcione igual en todos los motores.
        return Planeacion::query()->where('seccional_id', $seccional->id)->whereDate('fecha', $fecha)->first()
            ?? Planeacion::query()->create([
                'seccional_id' => $seccional->id,
                'fecha' => $fecha->toDateString(),
                'estado' => Planeacion::BORRADOR,
            ]);
    }

    /**
     * Guías que entran en la planeación: listas para asignar o reprogramadas para la fecha.
     *
     * @return Builder<Guia>
     */
    public function guiasPlaneables(Planeacion $planeacion): Builder
    {
        return Guia::query()
            ->where('seccional_id', $planeacion->seccional_id)
            ->planeablesPara($planeacion->fecha, $this->parametros->maxIntentos());
    }

    /**
     * Zonas del día con sus guías por asignar y lo que ya salió a ruta.
     *
     * @return Collection<int, array{zona: Zona, guias: int, rollos: int, urgentes: int, reprogramadas: int, en_ruta: int, repartidor_id: int|null}>
     */
    public function zonasDelDia(Planeacion $planeacion): Collection
    {
        $porZona = $this->guiasPlaneables($planeacion)
            ->selectRaw('zona_id, COUNT(*) as guias, SUM(rollos_solicitados) as rollos, SUM(es_urgente) as urgentes, SUM(CASE WHEN intentos > 0 THEN 1 ELSE 0 END) as reprogramadas')
            ->groupBy('zona_id')
            ->get()
            ->keyBy('zona_id');

        $enRuta = Parada::query()
            ->whereIn('estado', EstadoParada::activas())
            ->whereHas('ruta', fn (Builder $q) => $q->where('planeacion_id', $planeacion->id))
            ->selectRaw('zona_id, COUNT(*) as total')
            ->groupBy('zona_id')
            ->pluck('total', 'zona_id');

        $asignaciones = $planeacion->zonas()->pluck('repartidor_id', 'zona_id');

        return Zona::query()
            ->where('seccional_id', $planeacion->seccional_id)
            ->activas()
            ->orderBy('orden')->orderBy('codigo')
            ->get()
            ->map(fn (Zona $zona): array => [
                'zona' => $zona,
                'guias' => (int) ($porZona[$zona->id]->guias ?? 0),
                'rollos' => (int) ($porZona[$zona->id]->rollos ?? 0),
                'urgentes' => (int) ($porZona[$zona->id]->urgentes ?? 0),
                'reprogramadas' => (int) ($porZona[$zona->id]->reprogramadas ?? 0),
                'en_ruta' => (int) ($enRuta[$zona->id] ?? 0),
                'repartidor_id' => $asignaciones[$zona->id] ?? null,
            ])
            ->filter(fn (array $fila): bool => $fila['guias'] > 0 || $fila['en_ruta'] > 0)
            ->values();
    }

    public function asignarZona(Planeacion $planeacion, Zona $zona, ?Repartidor $repartidor): void
    {
        if ($zona->seccional_id !== $planeacion->seccional_id) {
            throw new OperacionNoPermitida('La zona no pertenece a la seccional de esta planeación.');
        }

        if ($repartidor !== null) {
            $this->exigirRepartidorValido($planeacion, $repartidor);
        }

        PlaneacionZona::query()->updateOrCreate(
            ['planeacion_id' => $planeacion->id, 'zona_id' => $zona->id],
            ['repartidor_id' => $repartidor?->id],
        );
    }

    /**
     * Copia la asignación zona → repartidor de la última planeación publicada.
     *
     * @return int Zonas que quedaron asignadas.
     */
    public function repetirAnterior(Planeacion $planeacion): int
    {
        $anterior = Planeacion::query()
            ->where('seccional_id', $planeacion->seccional_id)
            ->whereDate('fecha', '<', $planeacion->fecha)
            ->where('estado', Planeacion::PUBLICADA)
            ->latest('fecha')
            ->first();

        if ($anterior === null) {
            throw new OperacionNoPermitida('Todavía no hay una planeación anterior publicada para repetir.');
        }

        $disponibles = Repartidor::query()
            ->where('seccional_id', $planeacion->seccional_id)
            ->activos()->where('disponible', true)
            ->pluck('id')->flip();

        $zonasDeHoy = $this->zonasDelDia($planeacion)->where('guias', '>', 0)->pluck('zona.id')->flip();
        $copiadas = 0;

        foreach ($anterior->zonas()->whereNotNull('repartidor_id')->get() as $asignacion) {
            if (! isset($zonasDeHoy[$asignacion->zona_id], $disponibles[$asignacion->repartidor_id])) {
                continue;
            }

            PlaneacionZona::query()->updateOrCreate(
                ['planeacion_id' => $planeacion->id, 'zona_id' => $asignacion->zona_id],
                ['repartidor_id' => $asignacion->repartidor_id],
            );
            $copiadas++;
        }

        return $copiadas;
    }

    /**
     * Publica la planeación: crea o amplía la ruta de cada repartidor, descuenta
     * los rollos del inventario y avisa a la app. Se puede publicar varias veces
     * en el día; cada vez solo salen las guías que aún no estaban en ruta.
     *
     * @return array{guias: int, rollos: int, repartidores: int}
     */
    public function publicar(Planeacion $planeacion, User $usuario): array
    {
        $resultado = DB::transaction(function () use ($planeacion, $usuario): array {
            $asignaciones = $planeacion->zonas()->whereNotNull('repartidor_id')->with(['zona', 'repartidor.user'])->get();
            $porRuta = [];

            foreach ($asignaciones as $asignacion) {
                $guias = $this->guiasPlaneables($planeacion)->where('zona_id', $asignacion->zona_id)
                    ->orderByDesc('es_urgente')->orderBy('ans_vence_at')->lockForUpdate()->get();

                if ($guias->isEmpty()) {
                    continue;
                }

                $ruta = $this->rutaPara($planeacion, $asignacion->repartidor);
                $porRuta[$ruta->id] ??= ['ruta' => $ruta, 'guias' => 0, 'rollos' => 0, 'zonas' => []];

                foreach ($guias as $guia) {
                    $this->agregarParada($ruta, $guia, $usuario, 'planeación del día');
                    $porRuta[$ruta->id]['guias']++;
                    $porRuta[$ruta->id]['rollos'] += $guia->rollos_solicitados;
                }

                $porRuta[$ruta->id]['zonas'][] = $asignacion->zona->codigo;
            }

            if ($porRuta === []) {
                throw new OperacionNoPermitida('No hay guías nuevas para publicar: asigna un repartidor a las zonas que tienen guías.');
            }

            foreach ($porRuta as $fila) {
                /** @var Ruta $ruta */
                $ruta = $fila['ruta'];
                $ruta->increment('rollos_salida', $fila['rollos']);
                $this->ordenarSegunMemoria($ruta);

                $this->inventario->salidaRuta(
                    $ruta,
                    $fila['rollos'],
                    $ruta->repartidor->nombre.' · '.implode(' + ', array_unique($fila['zonas'])),
                    $usuario,
                );
            }

            $planeacion->update([
                'estado' => Planeacion::PUBLICADA,
                'publicada_at' => now(),
                'publicada_por' => $usuario->id,
            ]);

            return $porRuta;
        });

        foreach ($resultado as $fila) {
            $this->push->enviar(
                $fila['ruta']->repartidor->user,
                'Tienes guías para hoy',
                $fila['guias'] === 1 ? 'Se te asignó 1 guía nueva.' : "Se te asignaron {$fila['guias']} guías. Organiza tu ruta.",
                ['tipo' => 'planeacion', 'ruta_id' => $fila['ruta']->id],
            );
        }

        activity('planeacion')->performedOn($planeacion)->causedBy($usuario)
            ->withProperties(['guias' => array_sum(array_column($resultado, 'guias')), 'rollos' => array_sum(array_column($resultado, 'rollos'))])
            ->log('Planeación publicada');

        return [
            'guias' => array_sum(array_column($resultado, 'guias')),
            'rollos' => array_sum(array_column($resultado, 'rollos')),
            'repartidores' => count($resultado),
        ];
    }

    /**
     * Mueve una guía que todavía no ha salido a otra zona, solo para planearla
     * (no cambia la zona guardada de la dirección).
     */
    public function moverGuiaDeZona(Guia $guia, Zona $zona, User $usuario): void
    {
        if (! in_array($guia->estado, [EstadoGuia::ListaParaAsignar, EstadoGuia::NoEntregada], true)) {
            throw new OperacionNoPermitida('Solo se pueden mover guías que aún no han salido a ruta.');
        }

        if ($zona->seccional_id !== $guia->seccional_id) {
            throw new OperacionNoPermitida('La zona elegida pertenece a otra seccional.');
        }

        $guia->update(['zona_id' => $zona->id]);
        $this->bitacora->registrar($guia, TipoEvento::Actualizada, 'Guía movida a '.$zona->nombre_completo, 'solo para esta planeación', $usuario);
    }

    /**
     * Reasigna durante el día una parada pendiente a otro repartidor. Los rollos
     * pasan de una ruta a la otra sin volver a bodega.
     */
    public function reasignarParada(Parada $parada, Repartidor $destino, User $usuario): Parada
    {
        $nueva = DB::transaction(function () use ($parada, $destino, $usuario): Parada {
            $parada = Parada::query()->with(['ruta.planeacion', 'ruta.repartidor.user', 'guia'])->lockForUpdate()->findOrFail($parada->id);

            if (! $parada->estaPendiente()) {
                throw new OperacionNoPermitida('Solo se puede reasignar una parada que aún no ha sido visitada.');
            }

            if ($parada->ruta->repartidor_id === $destino->id) {
                throw new OperacionNoPermitida('La guía ya está en la ruta de ese repartidor.');
            }

            $this->exigirRepartidorValido($parada->ruta->planeacion, $destino);

            $origen = $parada->ruta;
            $rutaDestino = $this->rutaPara($origen->planeacion, $destino);
            $guia = $parada->guia;

            $parada->update(['estado' => EstadoParada::Reasignada]);
            $origen->decrement('rollos_salida', min($origen->rollos_salida, $guia->rollos_solicitados));

            $nueva = $this->agregarParada($rutaDestino, $guia, $usuario, 'reasignada desde '.$origen->repartidor->nombre, TipoEvento::Reasignada);
            $nueva->update(['es_nueva' => true, 'orden' => null]);
            $rutaDestino->increment('rollos_salida', $guia->rollos_solicitados);

            return $nueva;
        });

        $nueva->loadMissing(['ruta.repartidor.user', 'guia']);
        $this->push->enviar($nueva->ruta->repartidor->user, 'Nueva guía en tu ruta', $nueva->guia->nombre_comercio.' se agregó a tu ruta de hoy.', ['tipo' => 'cambio', 'ruta_id' => $nueva->ruta_id]);
        $this->push->enviar($parada->ruta->repartidor->user, 'Cambio en tu ruta', $nueva->guia->nombre_comercio.' pasó a otro repartidor.', ['tipo' => 'cambio', 'ruta_id' => $parada->ruta_id]);

        return $nueva;
    }

    /**
     * Sube una parada pendiente al primer lugar de la ruta (guía urgente).
     */
    public function priorizarParada(Parada $parada, User $usuario): void
    {
        $parada->loadMissing(['ruta.repartidor.user', 'guia']);

        if (! $parada->estaPendiente()) {
            throw new OperacionNoPermitida('La parada ya fue visitada.');
        }

        DB::transaction(function () use ($parada): void {
            $pendientes = $parada->ruta->paradas()->where('estado', EstadoParada::Pendiente)->enOrden()->get()
                ->reject(fn (Parada $p): bool => $p->id === $parada->id)
                ->prepend($parada)
                ->values();

            $base = (int) $parada->ruta->paradas()->whereIn('estado', EstadoParada::visitadas())->count();

            foreach ($pendientes as $indice => $pendiente) {
                $pendiente->update(['orden' => $base + $indice + 1, 'es_nueva' => false]);
            }
        });

        $this->bitacora->registrar($parada->guia, TipoEvento::Actualizada, 'Priorizada en la ruta', 'por '.$usuario->name, $usuario);
        $this->push->enviar($parada->ruta->repartidor->user, 'Guía urgente priorizada', $parada->guia->nombre_comercio.' pasó al primer lugar de tu ruta.', ['tipo' => 'urgente', 'ruta_id' => $parada->ruta_id]);
    }

    /**
     * Carga de guías por repartidor para el panel lateral de la planeación.
     *
     * @return Collection<int, array{repartidor: Repartidor, guias: int, zonas: int, en_ruta: int}>
     */
    public function cargaPorRepartidor(Planeacion $planeacion): Collection
    {
        $zonas = $this->zonasDelDia($planeacion);

        $enRuta = Ruta::query()->where('planeacion_id', $planeacion->id)->withCount('paradas')->pluck('paradas_count', 'repartidor_id');

        return Repartidor::query()
            ->with('user')
            ->where('seccional_id', $planeacion->seccional_id)
            ->activos()
            ->get()
            ->map(function (Repartidor $repartidor) use ($zonas, $enRuta): array {
                $propias = $zonas->where('repartidor_id', $repartidor->id);

                return [
                    'repartidor' => $repartidor,
                    'guias' => (int) $propias->sum('guias') + (int) ($enRuta[$repartidor->id] ?? 0),
                    'zonas' => $propias->count(),
                    'en_ruta' => (int) ($enRuta[$repartidor->id] ?? 0),
                ];
            })
            ->sortBy([['repartidor.disponible', 'desc'], ['guias', 'desc'], ['repartidor.nombre', 'asc']])
            ->values();
    }

    private function rutaPara(Planeacion $planeacion, Repartidor $repartidor): Ruta
    {
        $repartidor->loadMissing('user');

        $ruta = Ruta::query()->where('repartidor_id', $repartidor->id)->whereDate('fecha', $planeacion->fecha)->first()
            ?? Ruta::query()->create([
                'repartidor_id' => $repartidor->id,
                'fecha' => $planeacion->fecha->toDateString(),
                'planeacion_id' => $planeacion->id,
                'seccional_id' => $planeacion->seccional_id,
                'estado' => EstadoRuta::Asignada,
            ]);

        if ($ruta->estaCerrada()) {
            throw new OperacionNoPermitida($repartidor->nombre.' ya cerró su ruta de este día; elige otro repartidor.');
        }

        return $ruta->setRelation('repartidor', $repartidor);
    }

    private function agregarParada(Ruta $ruta, Guia $guia, User $usuario, string $detalle, TipoEvento $evento = TipoEvento::Asignada): Parada
    {
        $parada = Parada::query()->updateOrCreate(
            ['ruta_id' => $ruta->id, 'guia_id' => $guia->id],
            ['zona_id' => $guia->zona_id, 'estado' => EstadoParada::Pendiente, 'visitada_at' => null, 'orden' => null, 'es_nueva' => true],
        );

        // Si la ruta ya está en curso, la guía entra directamente en reparto.
        $guia->update([
            'estado' => $ruta->estaEnCurso() ? EstadoGuia::EnReparto : EstadoGuia::Asignada,
            'repartidor_id' => $ruta->repartidor_id,
            'fecha_programada' => $ruta->fecha,
        ]);

        $this->bitacora->registrar($guia, $evento, 'Asignado a '.$ruta->repartidor->nombre, $detalle, $usuario);

        return $parada;
    }

    /**
     * Ubica las paradas recién agregadas con la memoria del repartidor (por zona
     * y dirección). Las que no tienen memoria quedan arriba como "sin ordenar".
     *
     * Las paradas que ya tenían orden lo conservan: en una segunda publicación
     * del día no se pierde lo que el repartidor organizó en la app ni una guía
     * priorizada desde el seguimiento.
     */
    private function ordenarSegunMemoria(Ruta $ruta): void
    {
        $pendientes = $ruta->paradas()->where('estado', EstadoParada::Pendiente)->with(['guia:id,direccion_id', 'zona:id,orden'])->get();

        $memoria = OrdenMemoria::query()
            ->where('repartidor_id', $ruta->repartidor_id)
            ->whereIn('direccion_id', $pendientes->pluck('guia.direccion_id'))
            ->get()
            ->keyBy(fn (OrdenMemoria $m): string => $m->zona_id.'-'.$m->direccion_id);

        $posicion = fn (Parada $p): ?int => $memoria->get($p->zona_id.'-'.$p->guia->direccion_id)?->posicion;

        $secuencia = $pendientes->whereNotNull('orden')->sortBy('orden')->values()->all();
        $recienAgregadas = $pendientes->whereNull('orden');

        $conocidas = $recienAgregadas
            ->filter(fn (Parada $p): bool => $posicion($p) !== null)
            ->sortBy([
                fn (Parada $a, Parada $b): int => ($a->zona->orden ?? 0) <=> ($b->zona->orden ?? 0),
                fn (Parada $a, Parada $b): int => $posicion($a) <=> $posicion($b),
            ])
            ->values();

        foreach ($conocidas as $parada) {
            array_splice($secuencia, $this->lugarSegunMemoria($secuencia, $parada, $posicion), 0, [$parada]);
        }

        $base = (int) $ruta->paradas()->whereIn('estado', EstadoParada::visitadas())->count();

        foreach ($secuencia as $indice => $parada) {
            $parada->update(['orden' => $base + $indice + 1, 'es_nueva' => false]);
        }

        $recienAgregadas
            ->reject(fn (Parada $p): bool => $posicion($p) !== null)
            ->each(fn (Parada $p) => $p->update(['orden' => null, 'es_nueva' => true]));
    }

    /**
     * Índice donde va una parada nueva dentro de la secuencia actual: justo
     * después de la última de su zona que el repartidor suele visitar antes; si
     * no hay ninguna, antes de la primera de su zona; si su zona no está, al final.
     *
     * @param  list<Parada>  $secuencia
     * @param  Closure(Parada): ?int  $posicion
     */
    private function lugarSegunMemoria(array $secuencia, Parada $nueva, Closure $posicion): int
    {
        $propia = $posicion($nueva);
        $despuesDeLaAnterior = null;
        $primeraDeSuZona = null;

        foreach ($secuencia as $indice => $parada) {
            if ($parada->zona_id !== $nueva->zona_id) {
                continue;
            }

            $primeraDeSuZona ??= $indice;
            $otra = $posicion($parada);

            if ($otra !== null && $otra <= $propia) {
                $despuesDeLaAnterior = $indice + 1;
            }
        }

        return $despuesDeLaAnterior ?? $primeraDeSuZona ?? count($secuencia);
    }

    private function exigirRepartidorValido(Planeacion $planeacion, Repartidor $repartidor): void
    {
        if ($repartidor->seccional_id !== $planeacion->seccional_id) {
            throw new OperacionNoPermitida($repartidor->nombre.' pertenece a otra seccional.');
        }

        if (! $repartidor->activo || ! $repartidor->disponible) {
            throw new OperacionNoPermitida($repartidor->nombre.' no está disponible hoy.');
        }
    }
}
