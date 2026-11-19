<?php

declare(strict_types=1);

namespace App\Services\Cargas;

use App\Enums\EstadoCarga;
use App\Enums\EstadoGuia;
use App\Enums\ResultadoFila;
use App\Enums\TipoEvento;
use App\Exceptions\ArchivoInvalido;
use App\Exceptions\OperacionNoPermitida;
use App\Models\Carga;
use App\Models\CargaFila;
use App\Models\Ciudad;
use App\Models\Comercio;
use App\Models\Direccion;
use App\Models\Guia;
use App\Models\Seccional;
use App\Models\User;
use App\Services\Bitacora;
use App\Services\Guias\ConsecutivoGuias;
use App\Services\Zonificacion\ZonificacionService;
use App\Support\Texto;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carga del Excel diario de Credibanco en dos pasos:
 * 1) procesar: valida el archivo y deja la vista previa para revisar;
 * 2) confirmar: crea las guías de los servicios nuevos.
 */
final class CargaService
{
    private const string DISCO = 'local';

    public function __construct(
        private readonly LectorExcelCredibanco $lector,
        private readonly AnalizadorFilas $analizador,
        private readonly ZonificacionService $zonificacion,
        private readonly ConsecutivoGuias $consecutivo,
        private readonly Bitacora $bitacora,
    ) {}

    public function procesar(UploadedFile $archivo, User $usuario): Carga
    {
        return $this->procesarRuta($archivo->getRealPath(), $archivo->getClientOriginalName(), $usuario);
    }

    /**
     * @throws ArchivoInvalido
     */
    public function procesarRuta(string $rutaAbsoluta, string $nombreOriginal, User $usuario): Carga
    {
        $leido = $this->lector->leer($rutaAbsoluta);
        $ahora = CarbonImmutable::now();

        $extension = Str::lower(pathinfo($nombreOriginal, PATHINFO_EXTENSION)) ?: 'xlsx';
        $destino = 'cargas/'.$ahora->format('Y/m').'/'.Str::uuid().'.'.$extension;
        Storage::disk(self::DISCO)->put($destino, (string) file_get_contents($rutaAbsoluta));

        $this->analizador->preparar($leido->filas);

        $analizadas = array_map(
            fn (array $fila): array => ['numero' => $fila['numero']] + $this->analizador->analizar($fila, $ahora),
            $leido->filas,
        );

        return DB::transaction(function () use ($leido, $analizadas, $usuario, $nombreOriginal, $destino, $rutaAbsoluta, $ahora): Carga {
            $carga = Carga::query()->create([
                'user_id' => $usuario->id,
                'nombre_archivo' => Str::limit($nombreOriginal, 190, ''),
                'ruta_archivo' => $destino,
                'hash_sha256' => hash_file('sha256', $rutaAbsoluta),
                'hoja' => $leido->hoja,
                'estado' => EstadoCarga::Pendiente,
                'columnas_reconocidas' => count($leido->columnasReconocidas),
                'total_filas' => count($analizadas),
                ...$this->contadores($analizadas),
            ]);

            foreach (array_chunk($analizadas, 200) as $lote) {
                CargaFila::query()->insert(array_map(fn (array $fila): array => [
                    'carga_id' => $carga->id,
                    'numero_fila' => $fila['numero'],
                    'servicio' => $fila['servicio'] === null ? null : Str::limit($fila['servicio'], 30, ''),
                    'resultado' => $fila['resultado']->value,
                    'datos' => json_encode($fila['datos'], JSON_UNESCAPED_UNICODE),
                    'alertas' => $fila['alertas'] === [] ? null : json_encode($fila['alertas'], JSON_UNESCAPED_UNICODE),
                    'zona_id' => $fila['zona_id'],
                    'zona_origen' => $fila['zona_origen'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ], $lote));
            }

            return $carga;
        });
    }

    /**
     * Crea las guías de los servicios nuevos de la carga.
     */
    public function confirmar(Carga $carga, User $usuario): Carga
    {
        return DB::transaction(function () use ($carga, $usuario): Carga {
            $carga = Carga::query()->lockForUpdate()->findOrFail($carga->id);

            if (! $carga->estaPendiente()) {
                throw new OperacionNoPermitida('Esta carga ya fue '.Str::lower($carga->estado->etiqueta()).'.');
            }

            $ahora = CarbonImmutable::now();
            $seccionales = Seccional::query()->get()->keyBy('id');
            $creadas = ['nuevos' => 0, 'con_zona' => 0, 'por_zonificar' => 0, 'urgentes' => 0, 'total_rollos' => 0, 'duplicados' => 0];

            $filas = $carga->filas()->where('resultado', ResultadoFila::Nuevo)->get();

            foreach ($filas as $fila) {
                $datos = $fila->datos;

                // Otra carga pudo traer el mismo servicio mientras esta estaba en revisión.
                if (Guia::query()->where('servicio', $datos['servicio'])->exists()) {
                    $fila->update(['resultado' => ResultadoFila::Duplicado]);
                    $creadas['duplicados']++;

                    continue;
                }

                $guia = $this->crearGuia($carga, $fila, $seccionales->get($datos['seccional_id']), $usuario, $ahora);

                $creadas['nuevos']++;
                $creadas['total_rollos'] += $guia->rollos_solicitados;
                $creadas[$guia->zona_id ? 'con_zona' : 'por_zonificar']++;
                $creadas['urgentes'] += $guia->es_urgente ? 1 : 0;
            }

            $carga->update([
                'estado' => EstadoCarga::Confirmada,
                'confirmada_at' => $ahora,
                'nuevos' => $creadas['nuevos'],
                'con_zona' => $creadas['con_zona'],
                'por_zonificar' => $creadas['por_zonificar'],
                'urgentes' => $creadas['urgentes'],
                'total_rollos' => $creadas['total_rollos'],
                'duplicados' => $carga->duplicados + $creadas['duplicados'],
            ]);

            activity('cargas')
                ->performedOn($carga)
                ->causedBy($usuario)
                ->withProperties(['archivo' => $carga->nombre_archivo, 'servicios' => $creadas['nuevos'], 'rollos' => $creadas['total_rollos']])
                ->log("Carga confirmada: {$creadas['nuevos']} servicios nuevos");

            return $carga;
        });
    }

    public function cancelar(Carga $carga, User $usuario): void
    {
        if (! $carga->estaPendiente()) {
            throw new OperacionNoPermitida('Solo se puede cancelar una carga que está en revisión.');
        }

        $carga->update(['estado' => EstadoCarga::Cancelada]);

        activity('cargas')->performedOn($carga)->causedBy($usuario)->log('Carga cancelada antes de confirmar');
    }

    private function crearGuia(Carga $carga, CargaFila $fila, Seccional $seccional, User $usuario, CarbonImmutable $ahora): Guia
    {
        $datos = $fila->datos;

        $ciudad = $datos['ciudad_id'] !== null
            ? Ciudad::query()->findOrFail($datos['ciudad_id'])
            : Ciudad::query()->firstOrCreate(
                ['nombre_normalizado' => Texto::claveLugar($datos['ciudad']), 'seccional_id' => $seccional->id],
                [
                    'nombre' => Texto::nombrePropio($datos['ciudad']),
                    'departamento' => Texto::nombrePropio($datos['departamento'] ?? ''),
                    'departamento_normalizado' => Texto::claveLugar($datos['departamento'] ?? ''),
                    'codigo_dane' => $datos['codigo_ciudad'] !== '0' ? $datos['codigo_ciudad'] : null,
                ],
            );

        $comercio = Comercio::query()->firstOrCreate(
            ['codigo_unico' => $datos['codigo_unico']],
            ['nombre' => $datos['nombre_comercio'], 'cadena' => $datos['cadena']],
        );

        $direccion = Direccion::query()->firstOrCreate(
            ['ciudad_id' => $ciudad->id, 'clave_normalizada' => Str::limit($datos['clave_direccion'], 190, '')],
            ['direccion' => Str::limit($datos['direccion'], 190, ''), 'es_rural' => $datos['es_rural']],
        );

        // Se vuelve a buscar la zona: pudo zonificarse mientras la carga estaba en revisión.
        $resuelta = $this->zonificacion->resolver($datos['codigo_unico'], $ciudad->id, $direccion->clave_normalizada, $seccional->id);
        $zona = $resuelta['zona'] ?? null;

        if ($zona !== null) {
            // El directorio y el comercio quedan alineados con la zona usada.
            $direccion->zona_id ??= $zona->id;
            $direccion->save();
            $comercio->zona_id ??= $zona->id;
            $comercio->save();
        }

        $guia = Guia::query()->create([
            'servicio' => $datos['servicio'],
            'numero_guia' => $this->consecutivo->siguiente($ahora),
            'carga_id' => $carga->id,
            'comercio_id' => $comercio->id,
            'direccion_id' => $direccion->id,
            'ciudad_id' => $ciudad->id,
            'seccional_id' => $seccional->id,
            'zona_id' => $zona?->id,
            'estado' => $zona ? EstadoGuia::ListaParaAsignar : EstadoGuia::PorZonificar,
            'nombre_comercio' => Str::limit($datos['nombre_comercio'], 160, ''),
            'cadena' => $datos['cadena'] ? Str::limit($datos['cadena'], 160, '') : null,
            'direccion_original' => Str::limit($datos['direccion'], 250, ''),
            // La dirección se guarda y se muestra tal como llega en el Excel de Credibanco.
            'direccion_normalizada' => Str::limit($datos['direccion'], 190, ''),
            'complemento' => null,
            'ciudad_original' => $datos['ciudad'],
            'departamento' => $datos['departamento'],
            'codigo_ciudad' => $datos['codigo_ciudad'],
            'geolocalizacion' => $datos['geolocalizacion'],
            'telefono' => $datos['telefono_limpio'],
            'contacto_nombre' => $datos['contacto_nombre'] ?? $datos['contacto'],
            'contacto_telefono' => $datos['contacto_telefono'],
            'horario_comercio' => $datos['horario_comercio'],
            'horario_atencion' => $datos['horario_atencion'],
            'rollos_solicitados' => $datos['rollos'],
            'observaciones' => $datos['observaciones'],
            'alertas' => $fila->alertas,
            'fecha_registro' => $datos['fecha_registro'],
            'ans_horas' => $datos['ans_horas'],
            'ans_vence_at' => $datos['ans_vence_at'],
            'es_urgente' => $datos['es_urgente'],
        ]);

        $this->bitacora->registrar(
            $guia, TipoEvento::SolicitudRegistrada, 'Solicitud registrada en Credibanco',
            ocurridoAt: $guia->fecha_registro, actualizarUltimoMovimiento: false,
        );

        $this->bitacora->registrar(
            $guia, TipoEvento::Cargada, 'Cargado por PMS desde el Excel diario', $usuario->name, $usuario, $ahora,
            ['carga_id' => $carga->id, 'fila' => $fila->numero_fila],
        );

        if ($zona !== null) {
            $this->bitacora->registrar(
                $guia, TipoEvento::ZonaAutomatica, 'Zona asignada automáticamente: '.$zona->nombre_completo,
                $resuelta['origen'] === ZonificacionService::ORIGEN_COMERCIO ? 'comercio ya conocido' : 'dirección ya conocida',
                ocurridoAt: $ahora,
            );
        }

        $fila->update(['guia_id' => $guia->id, 'zona_id' => $zona?->id, 'zona_origen' => $resuelta['origen'] ?? null]);

        return $guia;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, int>
     */
    private function contadores(array $filas): array
    {
        $nuevas = array_filter($filas, fn (array $f): bool => $f['resultado'] === ResultadoFila::Nuevo);

        return [
            'nuevos' => count($nuevas),
            'duplicados' => count(array_filter($filas, fn (array $f): bool => $f['resultado'] === ResultadoFila::Duplicado)),
            'errores' => count(array_filter($filas, fn (array $f): bool => $f['resultado'] === ResultadoFila::Error)),
            'con_zona' => count(array_filter($nuevas, fn (array $f): bool => $f['zona_id'] !== null)),
            'por_zonificar' => count(array_filter($nuevas, fn (array $f): bool => $f['zona_id'] === null)),
            'urgentes' => count(array_filter($nuevas, fn (array $f): bool => (bool) ($f['datos']['es_urgente'] ?? false))),
            'con_alertas' => count(array_filter($nuevas, fn (array $f): bool => $f['alertas'] !== [])),
            'total_rollos' => (int) array_sum(array_map(fn (array $f): int => (int) $f['datos']['rollos'], $nuevas)),
        ];
    }
}
