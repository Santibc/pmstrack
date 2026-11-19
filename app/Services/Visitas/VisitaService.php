<?php

declare(strict_types=1);

namespace App\Services\Visitas;

use App\Enums\EstadoGuia;
use App\Enums\EstadoParada;
use App\Enums\ResultadoVisita;
use App\Enums\TipoEvento;
use App\Enums\TipoEvidencia;
use App\Exceptions\OperacionNoPermitida;
use App\Models\Causal;
use App\Models\Guia;
use App\Models\Parada;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Visita;
use App\Services\Bitacora;
use App\Services\Calendario\CalendarioLaboral;
use App\Services\Parametros;
use App\Services\Rutas\RutaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Registro de visitas con evidencia (documento técnico 5.4 y 5.5).
 */
final class VisitaService
{
    public function __construct(
        private readonly Bitacora $bitacora,
        private readonly Parametros $parametros,
        private readonly CalendarioLaboral $calendario,
        private readonly RutaService $rutas,
        private readonly MarcaDeAgua $marcaDeAgua,
    ) {}

    /**
     * Registra el resultado de una parada. Es idempotente por uuid: si la app
     * reintenta el envío (sin señal), devuelve la visita ya guardada.
     */
    public function registrar(Parada $parada, Repartidor $repartidor, DatosVisita $datos): Visita
    {
        $existente = Visita::query()->where('uuid', $datos->uuid)->first();

        if ($existente !== null) {
            if ($existente->parada_id !== $parada->id) {
                throw new OperacionNoPermitida('Ese identificador de visita ya se usó en otra parada.');
            }

            return $existente->load('evidencias');
        }

        $parada->loadMissing('guia');
        $repartidor->loadMissing('user');
        $this->validar($datos, $parada);
        $archivos = [];

        try {
            return DB::transaction(function () use ($parada, $repartidor, $datos, &$archivos): Visita {
                $parada = Parada::query()->with(['ruta', 'guia'])->lockForUpdate()->findOrFail($parada->id);
                $ruta = $parada->ruta;
                $guia = $parada->guia;

                if ($ruta->repartidor_id !== $repartidor->id) {
                    throw new OperacionNoPermitida('Esta parada no pertenece a tu ruta.');
                }

                if ($ruta->estaCerrada()) {
                    throw new OperacionNoPermitida('La ruta ya fue cerrada; no se pueden registrar más visitas.');
                }

                if (! $parada->estaPendiente()) {
                    throw new OperacionNoPermitida('Esta parada ya tiene un resultado registrado.');
                }

                // Una visita guardada sin señal puede llegar antes que el inicio de la ruta.
                if (! $ruta->estaEnCurso()) {
                    $this->rutas->iniciar($ruta, $datos->registradaAt);
                    $guia->refresh();
                }

                $intento = $guia->intentos + 1;
                $rollos = $datos->resultado === ResultadoVisita::Entregado ? (int) $datos->rollosEntregados : 0;

                $visita = Visita::query()->create([
                    'uuid' => $datos->uuid,
                    'parada_id' => $parada->id,
                    'guia_id' => $guia->id,
                    'ruta_id' => $ruta->id,
                    'repartidor_id' => $repartidor->id,
                    'resultado' => $datos->resultado,
                    'causal_id' => $datos->resultado === ResultadoVisita::Entregado ? null : $datos->causalId,
                    'observacion' => $datos->observacion,
                    'numero_intento' => $intento,
                    'rollos_solicitados' => $guia->rollos_solicitados,
                    'rollos_entregados' => $rollos,
                    'recibe_nombre' => $datos->recibeNombre,
                    'recibe_cedula' => $datos->recibeCedula,
                    'recibe_telefono' => $datos->recibeTelefono,
                    'autoriza_datos' => $datos->autorizaDatos,
                    'latitud' => $datos->latitud,
                    'longitud' => $datos->longitud,
                    'precision_m' => $datos->precision,
                    'registrada_at' => $datos->registradaAt,
                    'sincronizada_at' => now(),
                    'dispositivo' => $datos->dispositivo,
                    'app_version' => $datos->appVersion,
                ]);

                $archivos = $this->guardarEvidencias($visita, $guia, $datos);

                $parada->update(['estado' => $datos->resultado->estadoParada(), 'visitada_at' => $datos->registradaAt]);
                $this->actualizarGuia($guia, $visita, $repartidor);

                return $visita->load('evidencias');
            });
        } catch (\Throwable $error) {
            // Si la transacción falla, no deben quedar archivos huérfanos en el disco.
            foreach ($archivos as $ruta) {
                Storage::disk($this->disco())->delete($ruta);
            }

            throw $error;
        }
    }

    /**
     * Una visita registrada no se edita: la administradora la anula con
     * justificación y la guía vuelve a quedar pendiente.
     */
    public function anular(Visita $visita, User $usuario, string $motivo): void
    {
        if ($visita->estaAnulada()) {
            throw new OperacionNoPermitida('Esta visita ya estaba anulada.');
        }

        if (trim($motivo) === '') {
            throw new OperacionNoPermitida('La anulación necesita una justificación.');
        }

        DB::transaction(function () use ($visita, $usuario, $motivo): void {
            $visita->loadMissing(['parada.ruta', 'guia']);
            $visita->update(['anulada_at' => now(), 'anulada_por' => $usuario->id, 'anulacion_motivo' => $motivo]);

            $guia = $visita->guia;
            $rutaAbierta = ! $visita->parada->ruta->estaCerrada();

            if ($rutaAbierta) {
                $visita->parada->update(['estado' => EstadoParada::Pendiente, 'visitada_at' => null]);
            }

            $guia->update([
                'estado' => $rutaAbierta ? EstadoGuia::EnReparto : EstadoGuia::ListaParaAsignar,
                'repartidor_id' => $rutaAbierta ? $guia->repartidor_id : null,
                'intentos' => max(0, $guia->intentos - 1),
                'fecha_programada' => null,
                'entregada_at' => null,
                'rollos_entregados' => null,
                'cerrada_at' => null,
            ]);

            $this->bitacora->registrar(
                $guia, TipoEvento::VisitaAnulada, 'Visita anulada por la administración', $motivo, $usuario,
                meta: ['visita_id' => $visita->id, 'resultado' => $visita->resultado->value],
            );
        });
    }

    private function validar(DatosVisita $datos, Parada $parada): void
    {
        if ($datos->resultado === ResultadoVisita::Entregado) {
            $solicitados = $parada->guia->rollos_solicitados;

            match (true) {
                $datos->foto === null => throw new OperacionNoPermitida('La entrega necesita al menos una foto.'),
                $datos->firma === null => throw new OperacionNoPermitida('La entrega necesita la firma de quien recibe.'),
                blank($datos->recibeNombre) => throw new OperacionNoPermitida('Falta el nombre de quien recibe.'),
                blank($datos->recibeCedula) => throw new OperacionNoPermitida('Falta la cédula de quien recibe.'),
                ! $datos->autorizaDatos => throw new OperacionNoPermitida('Quien recibe debe autorizar el tratamiento de sus datos personales.'),
                $datos->rollosEntregados === null || $datos->rollosEntregados < 1 => throw new OperacionNoPermitida('Indica cuántos rollos se entregaron.'),
                $datos->rollosEntregados > $solicitados => throw new OperacionNoPermitida("No se pueden entregar más rollos de los solicitados ({$solicitados})."),
                default => null,
            };

            return;
        }

        if (blank($datos->observacion)) {
            throw new OperacionNoPermitida('La observación es obligatoria cuando no se entrega.');
        }

        $causal = $datos->causalId ? Causal::query()->find($datos->causalId) : null;

        if ($causal === null || ! $causal->activo || $causal->tipo !== $datos->resultado->tipoCausal()) {
            throw new OperacionNoPermitida('Elige un motivo válido del catálogo.');
        }
    }

    private function actualizarGuia(Guia $guia, Visita $visita, Repartidor $repartidor): void
    {
        $visita->loadMissing('causal');
        $momento = $visita->registrada_at;
        $sincronizado = 'sincronizado '.$visita->sincronizada_at->isoFormat('h:mm a');

        if ($visita->resultado === ResultadoVisita::Entregado) {
            $guia->update([
                'estado' => EstadoGuia::Entregada,
                'intentos' => $visita->numero_intento,
                'entregada_at' => $momento,
                'rollos_entregados' => $visita->rollos_entregados,
                'cerrada_at' => $momento,
                'fecha_programada' => null,
            ]);

            $titulo = $visita->esParcial()
                ? "Entrega parcial: {$visita->rollos_entregados} de {$visita->rollos_solicitados} rollos"
                : 'Entregado con foto, firma y GPS';

            $this->bitacora->registrar($guia, TipoEvento::Entregada, $titulo, $sincronizado, $repartidor->user, $momento, ['visita_id' => $visita->id]);

            return;
        }

        $motivo = $visita->causal?->nombre ?? 'sin motivo';

        if ($visita->resultado === ResultadoVisita::Anulado) {
            $guia->update(['estado' => EstadoGuia::Anulada, 'intentos' => $visita->numero_intento, 'cerrada_at' => $momento, 'fecha_programada' => null]);
            $this->bitacora->registrar($guia, TipoEvento::Anulada, 'Anulado · '.$motivo, $visita->observacion, $repartidor->user, $momento, ['visita_id' => $visita->id]);

            return;
        }

        // No entregado: se reprograma para el siguiente día hábil y suma un intento.
        $maximo = $this->parametros->maxIntentos();
        $siguiente = $this->calendario->siguienteDiaHabil($momento);

        $guia->update([
            'estado' => EstadoGuia::NoEntregada,
            'intentos' => $visita->numero_intento,
            'fecha_programada' => $siguiente->toDateString(),
        ]);

        $this->bitacora->registrar(
            $guia, TipoEvento::NoEntregada, "No entregado · {$motivo}",
            "intento {$visita->numero_intento} de {$maximo}", $repartidor->user, $momento, ['visita_id' => $visita->id],
        );

        $this->bitacora->registrar(
            $guia, TipoEvento::Reprogramada,
            $visita->numero_intento >= $maximo
                ? "Llegó a {$maximo} intentos: la administradora decide si se devuelve a Credibanco"
                : 'Se reprograma para el '.$siguiente->isoFormat('dddd D [de] MMM'),
            ocurridoAt: $momento,
            actualizarUltimoMovimiento: false,
        );
    }

    /**
     * @return list<string> Rutas de los archivos guardados.
     */
    private function guardarEvidencias(Visita $visita, Guia $guia, DatosVisita $datos): array
    {
        $guardados = [];
        $carpeta = 'evidencias/'.$visita->registrada_at->format('Y/m').'/'.$guia->id;
        $marca = $this->textoMarca($visita, $guia);

        foreach ([[TipoEvidencia::Foto, $datos->foto], [TipoEvidencia::Fachada, $datos->fachada]] as [$tipo, $archivo]) {
            if (! $archivo instanceof UploadedFile) {
                continue;
            }

            $procesada = $this->marcaDeAgua->aplicar(
                $archivo->getRealPath(), $marca,
                (int) config('pms.evidencias.ancho_maximo'), (int) config('pms.evidencias.calidad_jpeg'),
            );

            $guardados[] = $this->guardar($visita, $tipo, $carpeta, 'jpg', $procesada['contenido'], $procesada['mime'], $procesada['ancho'], $procesada['alto']);
        }

        if ($datos->firma instanceof UploadedFile) {
            $contenido = (string) file_get_contents($datos->firma->getRealPath());
            $medidas = @getimagesizefromstring($contenido) ?: [null, null];
            $extension = $datos->firma->getMimeType() === 'image/jpeg' ? 'jpg' : 'png';

            $guardados[] = $this->guardar($visita, TipoEvidencia::Firma, $carpeta, $extension, $contenido, (string) $datos->firma->getMimeType(), $medidas[0], $medidas[1]);
        }

        return $guardados;
    }

    private function guardar(Visita $visita, TipoEvidencia $tipo, string $carpeta, string $extension, string $contenido, string $mime, ?int $ancho, ?int $alto): string
    {
        $ruta = $carpeta.'/'.Str::uuid().'-'.$tipo->value.'.'.$extension;
        Storage::disk($this->disco())->put($ruta, $contenido);

        $visita->evidencias()->create([
            'tipo' => $tipo,
            'disk' => $this->disco(),
            'ruta' => $ruta,
            'mime' => $mime,
            'tamano' => strlen($contenido),
            'ancho' => $ancho,
            'alto' => $alto,
            // Huella del archivo tal como queda guardado (con la marca de agua).
            'sha256' => hash('sha256', $contenido),
        ]);

        return $ruta;
    }

    /**
     * "23/10/2026 11:32:08 · 4.6985, -74.0412 · Guía OCT-D-2147".
     */
    private function textoMarca(Visita $visita, Guia $guia): string
    {
        $coordenadas = $visita->tieneUbicacion()
            ? sprintf('%.4f, %.4f', $visita->latitud, $visita->longitud)
            : 'sin GPS';

        return $visita->registrada_at->format('d/m/Y H:i:s').' · '.$coordenadas.' · Guía '.$guia->numero_guia;
    }

    private function disco(): string
    {
        return (string) config('pms.evidencias.disco');
    }
}
