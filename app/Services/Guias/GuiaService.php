<?php

declare(strict_types=1);

namespace App\Services\Guias;

use App\Enums\EstadoGuia;
use App\Enums\EstadoParada;
use App\Enums\TipoCausal;
use App\Enums\TipoEvento;
use App\Exceptions\OperacionNoPermitida;
use App\Models\Causal;
use App\Models\Guia;
use App\Models\Parada;
use App\Models\User;
use App\Services\Bitacora;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Acciones de la administradora sobre una guía: reprogramar, anular con
 * motivo y devolver a Credibanco (documento técnico 4.1 y 5.5).
 */
final class GuiaService
{
    public function __construct(private readonly Bitacora $bitacora) {}

    public function reprogramar(Guia $guia, CarbonInterface $fecha, User $usuario, ?string $nota = null): void
    {
        if (! in_array($guia->estado, [EstadoGuia::NoEntregada, EstadoGuia::ListaParaAsignar], true)) {
            throw new OperacionNoPermitida('Solo se reprograman guías no entregadas o que aún no han salido a ruta.');
        }

        if ($guia->zona_id === null) {
            throw new OperacionNoPermitida('La guía aún no tiene zona: zonifícala primero.');
        }

        if ($fecha->lt(today())) {
            throw new OperacionNoPermitida('La fecha de reprogramación no puede ser anterior a hoy.');
        }

        $guia->update([
            'estado' => EstadoGuia::ListaParaAsignar,
            'fecha_programada' => $fecha->toDateString(),
            'repartidor_id' => null,
        ]);

        $this->bitacora->registrar(
            $guia, TipoEvento::Reprogramada, 'Reprogramada para el '.$fecha->isoFormat('dddd D [de] MMM'),
            $nota ?: 'por '.$usuario->name, $usuario,
        );
    }

    /**
     * Anulación por la administradora (comercio inexistente, cerrado o por
     * solicitud de Credibanco). Es un estado final.
     */
    public function anular(Guia $guia, Causal $causal, string $observacion, User $usuario): void
    {
        if ($guia->estado->esFinal()) {
            throw new OperacionNoPermitida('La guía ya está en un estado final ('.$guia->estado->etiqueta().').');
        }

        if ($causal->tipo !== TipoCausal::Anulado) {
            throw new OperacionNoPermitida('Elige un motivo de anulación.');
        }

        if (trim($observacion) === '') {
            throw new OperacionNoPermitida('La anulación necesita una observación.');
        }

        DB::transaction(function () use ($guia, $causal, $observacion, $usuario): void {
            // Si está en una ruta abierta, la parada queda anulada y sus rollos vuelven en el cierre.
            Parada::query()->where('guia_id', $guia->id)->where('estado', EstadoParada::Pendiente)
                ->update(['estado' => EstadoParada::Anulada, 'visitada_at' => now()]);

            $guia->update(['estado' => EstadoGuia::Anulada, 'cerrada_at' => now(), 'fecha_programada' => null]);

            $this->bitacora->registrar(
                $guia, TipoEvento::Anulada, 'Anulado por la administración · '.$causal->nombre, $observacion, $usuario,
                meta: ['causal_id' => $causal->id],
            );
        });
    }

    /**
     * Tras los intentos fallidos, la administradora decide devolver el servicio.
     */
    public function devolverACredibanco(Guia $guia, string $observacion, User $usuario): void
    {
        if ($guia->estado !== EstadoGuia::NoEntregada) {
            throw new OperacionNoPermitida('Solo se devuelven a Credibanco las guías no entregadas.');
        }

        if (trim($observacion) === '') {
            throw new OperacionNoPermitida('La devolución necesita una observación.');
        }

        $guia->update(['estado' => EstadoGuia::DevueltaCredibanco, 'cerrada_at' => now(), 'fecha_programada' => null, 'repartidor_id' => null]);

        $this->bitacora->registrar(
            $guia, TipoEvento::DevueltaCredibanco, "Devuelta a Credibanco tras {$guia->intentos} ".($guia->intentos === 1 ? 'intento' : 'intentos'),
            $observacion, $usuario,
        );
    }
}
