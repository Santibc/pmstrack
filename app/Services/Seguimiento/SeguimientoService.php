<?php

declare(strict_types=1);

namespace App\Services\Seguimiento;

use App\Enums\EstadoParada;
use App\Enums\EstadoRuta;
use App\Enums\ResultadoVisita;
use App\Enums\SemaforoAns;
use App\Models\Novedad;
use App\Models\Parada;
use App\Models\Ruta;
use App\Models\Seccional;
use App\Models\Visita;
use App\Services\Parametros;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Seguimiento en vivo (documento técnico 4.1 y 5.5): tablero por repartidor,
 * sin mapa ni rastreo. Todo sale de lo que el repartidor registra en la app.
 *
 * Alertas automáticas: ANS en riesgo, repartidor sin actividad, ruta sin
 * iniciar y cierre de ruta que no cuadra.
 */
final class SeguimientoService
{
    public const string SIN_ACTIVIDAD = 'sin_actividad';

    public const string SIN_INICIAR = 'sin_iniciar';

    public const string EN_REPARTO = 'en_reparto';

    public const string POR_SALIR = 'por_salir';

    public const string CERRADA = 'cerrada';

    public function __construct(private readonly Parametros $parametros) {}

    /**
     * @return array{rutas: Collection<int, array<string, mixed>>, totales: array<string, int>, alertas: Collection<int, array<string, mixed>>, novedades: Collection<int, Novedad>, actividad: Collection<int, array<string, mixed>>}
     */
    public function tablero(Seccional $seccional, CarbonInterface $fecha): array
    {
        $ahora = CarbonImmutable::now();
        $esHoy = $fecha->isSameDay($ahora);

        $rutas = Ruta::query()
            ->with([
                'repartidor.user',
                'paradas' => fn ($q) => $q->enOrden(),
                'paradas.guia',
                'paradas.zona',
                'paradas.visita.causal',
            ])
            ->where('seccional_id', $seccional->id)
            ->whereDate('fecha', $fecha)
            ->get();

        $filas = $rutas->map(fn (Ruta $ruta): array => $this->fila($ruta, $ahora, $esHoy));

        return [
            'rutas' => $filas->sortBy(fn (array $fila): array => [$fila['alerta'] ? 0 : 1, $fila['estado'] === self::CERRADA ? 1 : 0, $fila['ruta']->repartidor->nombre])->values(),
            'totales' => [
                'entregadas' => (int) $filas->sum('entregadas'),
                'no_entregadas' => (int) $filas->sum('no_entregadas'),
                'anuladas' => (int) $filas->sum('anuladas'),
                'pendientes' => (int) $filas->sum('pendientes'),
                'guias' => (int) $filas->sum('total'),
            ],
            'alertas' => $this->alertas($rutas, $filas, $ahora, $esHoy),
            'novedades' => Novedad::query()
                ->with(['repartidor.user', 'guia'])
                ->whereHas('repartidor', fn ($q) => $q->where('seccional_id', $seccional->id))
                ->whereDate('reportada_at', $fecha)
                ->latest('reportada_at')
                ->get(),
            'actividad' => $this->actividad($rutas),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Ruta $ruta, CarbonImmutable $ahora, bool $esHoy): array
    {
        $paradas = $ruta->paradas;
        $pendientes = $paradas->where('estado', EstadoParada::Pendiente);
        $ultima = $paradas->filter(fn (Parada $p): bool => $p->visita !== null)->sortByDesc('visitada_at')->first();
        $siguiente = $pendientes->first();
        $ultimaActividad = $ultima?->visitada_at ?? $ruta->iniciada_at;

        $minutos = $ultimaActividad ? (int) $ultimaActividad->diffInMinutes($ahora) : null;
        $limiteSinActividad = max(1, $this->parametros->entero('alerta_sin_actividad_min'));

        $estado = match (true) {
            $ruta->estaCerrada() => self::CERRADA,
            $ruta->estado === EstadoRuta::Asignada && $esHoy && $ahora->format('H:i') >= $this->parametros->texto('alerta_inicio_ruta_hora') => self::SIN_INICIAR,
            $ruta->estado === EstadoRuta::Asignada => self::POR_SALIR,
            $esHoy && $pendientes->isNotEmpty() && $minutos !== null && $minutos >= $limiteSinActividad => self::SIN_ACTIVIDAD,
            default => self::EN_REPARTO,
        };

        return [
            'ruta' => $ruta,
            'zonas' => $paradas->pluck('zona')->filter()->unique('id')->sortBy('orden')->map(fn ($zona): string => $zona->nombre_completo)->implode(' + '),
            'total' => $paradas->count(),
            'visitadas' => $paradas->count() - $pendientes->count(),
            'entregadas' => $paradas->where('estado', EstadoParada::Entregada)->count(),
            'no_entregadas' => $paradas->where('estado', EstadoParada::NoEntregada)->count(),
            'anuladas' => $paradas->where('estado', EstadoParada::Anulada)->count(),
            'pendientes' => $pendientes->count(),
            'ultima' => $ultima,
            'siguiente' => $siguiente,
            'minutos_sin_actividad' => $minutos,
            'estado' => $estado,
            'alerta' => in_array($estado, [self::SIN_ACTIVIDAD, self::SIN_INICIAR], true) || ($ruta->estaCerrada() && $ruta->cuadre_ok === false),
        ];
    }

    /**
     * @param  Collection<int, Ruta>  $rutas
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    private function alertas(Collection $rutas, Collection $filas, CarbonImmutable $ahora, bool $esHoy): Collection
    {
        $alertas = collect();

        // ANS en riesgo: guías pendientes con menos del 25 % del tiempo (o ya vencidas).
        foreach ($rutas as $ruta) {
            foreach ($ruta->paradas->where('estado', EstadoParada::Pendiente) as $parada) {
                $semaforo = $parada->guia->semaforo($ahora);

                if (! in_array($semaforo, [SemaforoAns::EnRiesgo, SemaforoAns::Vencido], true)) {
                    continue;
                }

                $vence = $parada->guia->ans_vence_at;

                $alertas->push([
                    'tipo' => 'ans',
                    'gravedad' => $semaforo === SemaforoAns::Vencido ? 0 : 1,
                    'titulo' => $semaforo === SemaforoAns::Vencido ? 'ANS vencido' : 'ANS en riesgo',
                    'texto' => ($parada->guia->es_urgente ? 'Guía urgente ' : 'Guía ').$parada->guia->servicio." (ANS {$parada->guia->ans_horas} h) "
                        .($semaforo === SemaforoAns::Vencido ? 'venció ' : 'vence ')
                        .($vence->isSameDay($ahora) ? 'a las '.$vence->isoFormat('h:mm a') : 'el '.$vence->isoFormat('D MMM, h:mm a'))
                        .' y aún no ha sido visitada. La lleva '.$ruta->repartidor->nombre.'.',
                    'parada_id' => $parada->id,
                    'servicio' => $parada->guia->servicio,
                    'vence' => $vence,
                ]);
            }
        }

        foreach ($filas as $fila) {
            /** @var Ruta $ruta */
            $ruta = $fila['ruta'];

            if ($fila['estado'] === self::SIN_ACTIVIDAD) {
                $alertas->push([
                    'tipo' => 'actividad', 'gravedad' => 2, 'titulo' => 'Sin actividad',
                    'texto' => "{$ruta->repartidor->nombre} no registra visitas hace {$fila['minutos_sin_actividad']} min y tiene {$fila['pendientes']} ".($fila['pendientes'] === 1 ? 'parada pendiente.' : 'paradas pendientes.'),
                ]);
            }

            if ($fila['estado'] === self::SIN_INICIAR) {
                $alertas->push([
                    'tipo' => 'inicio', 'gravedad' => 2, 'titulo' => 'Ruta sin iniciar',
                    'texto' => "{$ruta->repartidor->nombre} aún no ha tocado «Empezar ruta» ({$fila['total']} guías asignadas).",
                ]);
            }

            if ($ruta->estaCerrada() && $ruta->cuadre_ok === false) {
                $alertas->push([
                    'tipo' => 'cuadre', 'gravedad' => 1, 'titulo' => 'El cierre no cuadra',
                    'texto' => "{$ruta->repartidor->nombre} devolvió {$ruta->rollos_devueltos_declarados} rollos y se esperaban {$ruta->rollos_devueltos_esperados}.",
                ]);
            }
        }

        return $alertas->sortBy('gravedad')->values();
    }

    /**
     * Últimos registros del día: visitas y cierres de ruta.
     *
     * @param  Collection<int, Ruta>  $rutas
     * @return Collection<int, array<string, mixed>>
     */
    private function actividad(Collection $rutas): Collection
    {
        $visitas = Visita::query()
            ->with(['guia', 'repartidor.user', 'causal'])
            ->vigentes()
            ->whereIn('ruta_id', $rutas->pluck('id'))
            ->latest('registrada_at')
            ->limit(12)
            ->get()
            ->map(fn (Visita $visita): array => [
                'cuando' => $visita->registrada_at,
                'tono' => $visita->resultado->color(),
                'quien' => $visita->repartidor->nombre_corto,
                'texto' => match ($visita->resultado) {
                    ResultadoVisita::Entregado => 'entregó en '.$visita->guia->nombre_comercio,
                    ResultadoVisita::NoEntregado => 'no pudo entregar en '.$visita->guia->nombre_comercio,
                    ResultadoVisita::Anulado => 'anuló '.$visita->guia->nombre_comercio,
                },
                'detalle' => $visita->resultado === ResultadoVisita::Entregado
                    ? ($visita->esParcial() ? "parcial: {$visita->rollos_entregados} de {$visita->rollos_solicitados} rollos" : 'con foto, firma y GPS')
                    : $visita->causal?->nombre,
                'servicio' => $visita->guia->servicio,
            ]);

        $cierres = $rutas->filter(fn (Ruta $ruta): bool => $ruta->estaCerrada())->map(fn (Ruta $ruta): array => [
            'cuando' => $ruta->cerrada_at,
            'tono' => 'dark',
            'quien' => $ruta->repartidor->nombre_corto,
            'texto' => 'cerró la ruta: '.$ruta->paradas->filter(fn (Parada $p): bool => $p->estado->fueVisitada())->count().' de '.$ruta->paradas->count().' visitadas',
            'detalle' => $ruta->cuadre_ok === false ? 'el cierre no cuadra' : null,
            'servicio' => null,
        ]);

        return $visitas->concat($cierres)->sortByDesc('cuando')->take(12)->values();
    }
}
