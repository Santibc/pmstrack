<?php

declare(strict_types=1);

namespace App\Services\Guias;

use App\Enums\EstadoGuia;
use App\Enums\TipoEvento;
use App\Models\Guia;
use App\Models\GuiaEvento;
use Carbon\CarbonInterface;

/**
 * Los cuatro pasos que ve Credibanco y el comercio en la consulta de un
 * servicio: recibido, asignado a ruta, en reparto y resultado.
 */
final class LineaDeTiempo
{
    public const string HECHO = 'hecho';

    public const string ACTUAL = 'actual';

    public const string PENDIENTE = 'pendiente';

    /**
     * Requiere las relaciones: eventos, zona, repartidor.user, ultimaVisita.causal.
     *
     * @return list<array{titulo: string, estado: string, tono: string, fecha: CarbonInterface|null, detalle: string|null}>
     */
    public function pasos(Guia $guia): array
    {
        $eventos = $guia->eventos;
        $ultimo = fn (TipoEvento ...$tipos): ?GuiaEvento => $eventos->last(fn (GuiaEvento $e): bool => in_array($e->tipo, $tipos, true));

        $cargada = $ultimo(TipoEvento::Cargada);
        $asignada = $ultimo(TipoEvento::Asignada, TipoEvento::Reasignada);
        $iniciada = $ultimo(TipoEvento::RutaIniciada);
        $avance = $this->avance($guia->estado);

        $pasos = [
            $this->paso('Recibido por PMS', 1, $avance, $cargada?->ocurrido_at ?? $guia->created_at, 'Llegó en el archivo diario de Credibanco'),
            $this->paso('Asignado a ruta', 2, $avance, $asignada?->ocurrido_at, $guia->zona ? 'Zona '.$guia->zona->nombre : null, 'A la espera de planeación'),
            $this->paso('En reparto', 3, $avance, $iniciada?->ocurrido_at, $guia->repartidor ? 'Repartidor '.$guia->repartidor->nombre_corto : null, 'Cuando el repartidor inicie su ruta'),
        ];

        $pasos[] = $this->resultado($guia, $avance);

        return $pasos;
    }

    /**
     * @return array{titulo: string, estado: string, tono: string, fecha: CarbonInterface|null, detalle: string|null}
     */
    private function resultado(Guia $guia, int $avance): array
    {
        $visita = $guia->ultimaVisita;
        $fecha = $visita?->registrada_at ?? $guia->cerrada_at;

        return match ($guia->estado) {
            EstadoGuia::Entregada => [
                'titulo' => 'Entregado', 'estado' => self::HECHO, 'tono' => 'ok', 'fecha' => $guia->entregada_at,
                'detalle' => $visita?->esParcial() ? "Entrega parcial: {$visita->rollos_entregados} de {$visita->rollos_solicitados} rollos" : 'Con foto, firma y ubicación GPS',
            ],
            EstadoGuia::NoEntregada => [
                'titulo' => 'No entregado', 'estado' => self::ACTUAL, 'tono' => 'warn', 'fecha' => $fecha,
                'detalle' => trim(($visita?->causal?->nombre ?? '').($guia->fecha_programada ? ' · se reprograma para el '.$guia->fecha_programada->isoFormat('dddd D [de] MMM') : '')),
            ],
            EstadoGuia::Anulada => [
                'titulo' => 'Anulado', 'estado' => self::HECHO, 'tono' => 'bad', 'fecha' => $guia->cerrada_at,
                'detalle' => $visita?->causal?->nombre ?? 'Anulado por la administración de PMS',
            ],
            EstadoGuia::DevueltaCredibanco => [
                'titulo' => 'Devuelto a Credibanco', 'estado' => self::HECHO, 'tono' => 'bad', 'fecha' => $guia->cerrada_at,
                'detalle' => "Tras {$guia->intentos} ".($guia->intentos === 1 ? 'intento' : 'intentos').' de entrega',
            ],
            default => [
                'titulo' => 'Entregado', 'estado' => self::PENDIENTE, 'tono' => 'gray', 'fecha' => null,
                'detalle' => $guia->intentos > 0 ? 'Intento '.($guia->intentos + 1).' de entrega' : 'Con foto, firma y ubicación GPS',
            ],
        };
    }

    /**
     * @return array{titulo: string, estado: string, tono: string, fecha: CarbonInterface|null, detalle: string|null}
     */
    private function paso(string $titulo, int $numero, int $avance, ?CarbonInterface $fecha, ?string $detalle, ?string $pendiente = null): array
    {
        $hecho = $numero <= $avance;

        return [
            'titulo' => $titulo,
            'estado' => $hecho ? self::HECHO : self::PENDIENTE,
            'tono' => $hecho ? 'ok' : 'gray',
            'fecha' => $hecho ? $fecha : null,
            'detalle' => $hecho ? $detalle : $pendiente,
        ];
    }

    /**
     * Cuántos de los tres primeros pasos ya ocurrieron en el intento vigente.
     */
    private function avance(EstadoGuia $estado): int
    {
        return match ($estado) {
            EstadoGuia::Recibida, EstadoGuia::PorZonificar, EstadoGuia::ListaParaAsignar => 1,
            EstadoGuia::Asignada => 2,
            default => 3,
        };
    }
}
