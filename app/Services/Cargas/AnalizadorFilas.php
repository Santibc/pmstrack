<?php

declare(strict_types=1);

namespace App\Services\Cargas;

use App\Enums\ResultadoFila;
use App\Models\Ciudad;
use App\Models\Guia;
use App\Models\Seccional;
use App\Services\Ans\CalculadoraAns;
use App\Services\Direcciones\NormalizadorDirecciones;
use App\Services\Zonificacion\ZonificacionService;
use App\Support\Telefono;
use App\Support\Texto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use Throwable;

/**
 * Convierte cada fila del Excel de Credibanco en un servicio validado:
 * detecta duplicados, calcula el ANS, toma el contacto de las observaciones,
 * normaliza la dirección, busca su zona y levanta las alertas de datos.
 */
final class AnalizadorFilas
{
    public const string ALERTA_CIUDAD = 'ciudad_seccional';

    public const string ALERTA_CIUDAD_NUEVA = 'ciudad_nueva';

    public const string ALERTA_RURAL = 'direccion_rural';

    public const string ALERTA_TELEFONO = 'telefono_incompleto';

    public const string ALERTA_ROLLOS = 'rollos_difieren';

    public const string ALERTA_FECHA = 'sin_fecha_registro';

    public const string ALERTA_ANS = 'ans_por_defecto';

    public const string ALERTA_ERROR = 'error';

    public const string ALERTA_DUPLICADO = 'duplicado';

    /** @var Collection<string, Seccional> */
    private Collection $seccionales;

    /** @var Collection<string, Collection<int, Ciudad>> */
    private Collection $ciudades;

    /** @var Collection<string, Guia> Guías ya cargadas, por número de servicio. */
    private Collection $existentes;

    /** @var array<string, int> Servicio → fila donde apareció por primera vez en este archivo. */
    private array $vistos = [];

    public function __construct(
        private readonly NormalizadorDirecciones $normalizador,
        private readonly ExtractorContacto $extractor,
        private readonly CalculadoraAns $ans,
        private readonly ZonificacionService $zonificacion,
    ) {}

    /**
     * Carga los catálogos una sola vez antes de analizar las filas del archivo.
     *
     * @param  list<array{numero: int, valores: array<string, mixed>}>  $filas
     */
    public function preparar(array $filas): void
    {
        $this->vistos = [];
        $this->seccionales = Seccional::query()->activas()->get()->keyBy('nombre_normalizado');
        $this->ciudades = Ciudad::query()->get()->groupBy('nombre_normalizado');

        $servicios = collect($filas)
            ->map(fn (array $fila): ?string => $this->cadena($fila['valores']['SERVICIO'] ?? null))
            ->filter()
            ->unique()
            ->values();

        $this->existentes = $servicios->chunk(500)
            ->flatMap(fn (Collection $lote) => Guia::query()->whereIn('servicio', $lote->all())->get(['id', 'servicio', 'created_at']))
            ->keyBy('servicio');
    }

    /**
     * @param  array{numero: int, valores: array<string, mixed>}  $fila
     * @return array{servicio: string|null, resultado: ResultadoFila, datos: array<string, mixed>, alertas: list<array{codigo: string, mensaje: string}>, zona_id: int|null, zona_origen: string|null}
     */
    public function analizar(array $fila, CarbonImmutable $ahora): array
    {
        $v = $fila['valores'];
        $servicio = $this->cadena($v['SERVICIO'] ?? null);
        $codigoUnico = $this->cadena($v['CODIGO_UNICO'] ?? null);
        $nombreComercio = $this->cadena($v['NOMBRE_COMERCIO'] ?? null);
        $ciudadOriginal = $this->cadena($v['CIUDAD'] ?? null);
        $seccionalOriginal = $this->cadena($v['SECCIONAL'] ?? null);
        $direccionOriginal = $this->cadena($v['DIRECCION'] ?? null);
        $rollos = $this->entero($v['PAPELERIA_ENTREGAR'] ?? null);
        $observaciones = $this->cadena($v['OBSERVACIONES'] ?? null);
        $departamento = $this->cadena($v['DEPARTAMENTO'] ?? null);
        $seccional = $this->seccionales->get(Texto::claveLugar($seccionalOriginal));

        $datos = [
            'servicio' => $servicio,
            'cadena' => $this->cadena($v['CADENA'] ?? null),
            'codigo_unico' => $codigoUnico,
            'nombre_comercio' => $nombreComercio,
            'codigo_ciudad' => $this->cadena($v['CODIGO_CIUDAD'] ?? null),
            'ciudad' => $ciudadOriginal,
            'geolocalizacion' => $this->cadena($v['GEOLOCALIZACION'] ?? null),
            'seccional' => $seccionalOriginal,
            'departamento' => $departamento,
            'direccion' => $direccionOriginal,
            'telefono' => $this->cadena($v['TELEFONO'] ?? null),
            'contacto' => $this->cadena($v['CONTACTO'] ?? null),
            'horario_atencion' => $this->cadena($v['HORARIO_DE_ATENCION'] ?? null),
            'rollos' => $rollos,
            'observaciones' => $observaciones,
        ];

        $errores = array_values(array_filter([
            $servicio === null ? 'no trae número de SERVICIO' : null,
            $codigoUnico === null ? 'no trae CODIGO_UNICO' : null,
            $nombreComercio === null ? 'no trae NOMBRE_COMERCIO' : null,
            $direccionOriginal === null ? 'no trae DIRECCION' : null,
            $ciudadOriginal === null ? 'no trae CIUDAD' : null,
            $rollos === null || $rollos <= 0 ? 'PAPELERIA_ENTREGAR no es una cantidad válida' : null,
            $seccional === null ? 'la seccional "'.($seccionalOriginal ?? 'vacía').'" no existe en el sistema' : null,
        ]));

        if ($errores !== []) {
            return $this->resultado($servicio, ResultadoFila::Error, $datos, [
                $this->alerta(self::ALERTA_ERROR, implode('; ', $errores).'.'),
            ]);
        }

        if ($existente = $this->existentes->get($servicio)) {
            return $this->resultado($servicio, ResultadoFila::Duplicado, $datos, [
                $this->alerta(self::ALERTA_DUPLICADO, 'ya cargado el '.$existente->created_at->isoFormat('D [de] MMM').'.'),
            ]);
        }

        if (isset($this->vistos[$servicio])) {
            return $this->resultado($servicio, ResultadoFila::Duplicado, $datos, [
                $this->alerta(self::ALERTA_DUPLICADO, "repetido en la fila {$this->vistos[$servicio]} de este archivo."),
            ]);
        }

        $this->vistos[$servicio] = $fila['numero'];

        $alertas = [];
        $contacto = $this->extractor->extraer($observaciones);

        // Ciudad frente a seccional.
        [$ciudad, $alertaCiudad] = $this->resolverCiudad($ciudadOriginal, $seccional, $departamento, $contacto->ciudad);

        if ($alertaCiudad !== null) {
            $alertas[] = $alertaCiudad;
        }

        // Dirección.
        $direccion = $this->normalizador->normalizar($direccionOriginal, $ciudadOriginal, $datos['geolocalizacion']);

        if ($direccion->esRural) {
            $alertas[] = $this->alerta(self::ALERTA_RURAL, "dirección rural ({$direccionOriginal}); no tiene zona urbana definida.");
        }

        // Teléfono.
        $contactoUtil = Telefono::esCompleto($contacto->telefono);

        if (! Telefono::esCompleto($datos['telefono'])) {
            $descripcion = $datos['telefono'] === null ? 'sin teléfono' : "teléfono incompleto ({$datos['telefono']})";
            $alertas[] = $this->alerta(
                self::ALERTA_TELEFONO,
                $descripcion.($contactoUtil ? '; se tomó el número que viene en Observaciones.' : '; tampoco viene uno completo en Observaciones.'),
            );
        }

        // Rollos que pide la observación frente a la columna.
        if ($observaciones !== null && preg_match('/REQUIERE\s+(?:PAPELER[IÍ]A\s+)?(\d{1,4})\s+ROLLOS/iu', $observaciones, $m) && (int) $m[1] !== $rollos) {
            $alertas[] = $this->alerta(self::ALERTA_ROLLOS, "la observación pide {$m[1]} rollos y PAPELERIA_ENTREGAR trae {$rollos}.");
        }

        // ANS.
        $fechaRegistro = $this->fecha($v['FECHA_REGISTRO'] ?? null);

        if ($fechaRegistro === null) {
            $fechaRegistro = $ahora;
            $alertas[] = $this->alerta(self::ALERTA_FECHA, 'sin FECHA_REGISTRO; el ANS se cuenta desde la carga.');
        }

        $horas = $this->ans->extraerHoras($datos['horario_atencion']);

        if ($horas === null) {
            $horas = $this->ans->horasPorDefecto();
            $alertas[] = $this->alerta(self::ALERTA_ANS, "sin horas de ANS en HORARIO_DE_ATENCION; se usan {$horas} h.");
        }

        $zona = $this->zonificacion->resolver($codigoUnico, $ciudad?->id, $direccion->clave, $seccional->id);

        $datos += [
            'seccional_id' => $seccional->id,
            'ciudad_id' => $ciudad?->id,
            'direccion_normalizada' => $direccion->principal,
            'complemento' => $direccion->complemento,
            'clave_direccion' => $direccion->clave,
            'es_rural' => $direccion->esRural,
            'telefono_limpio' => Telefono::limpiar($datos['telefono']),
            'contacto_nombre' => $contacto->nombre,
            'contacto_telefono' => $contacto->telefono,
            'horario_comercio' => $contacto->horario,
            'fecha_registro' => $fechaRegistro->toDateTimeString(),
            'ans_horas' => $horas,
            'ans_vence_at' => $this->ans->vencimiento($fechaRegistro, $horas)->toDateTimeString(),
            'es_urgente' => $this->ans->esUrgente($horas),
        ];

        return $this->resultado($servicio, ResultadoFila::Nuevo, $datos, $alertas, $zona['zona']->id ?? null, $zona['origen'] ?? null);
    }

    /**
     * @return array{0: Ciudad|null, 1: array{codigo: string, mensaje: string}|null}
     */
    private function resolverCiudad(string $ciudadOriginal, Seccional $seccional, ?string $departamento, ?string $ciudadObservacion): array
    {
        $candidatas = $this->ciudades->get(Texto::claveLugar($ciudadOriginal), collect());
        $enSeccional = $candidatas->firstWhere('seccional_id', $seccional->id);

        if ($enSeccional !== null) {
            return [$enSeccional, null];
        }

        if ($candidatas->isEmpty()) {
            return [null, $this->alerta(
                self::ALERTA_CIUDAD_NUEVA,
                "la ciudad {$ciudadOriginal} no está en el catálogo; se creará en la seccional {$seccional->nombre}.",
            )];
        }

        // La ciudad existe, pero en otra seccional: manda la seccional (define bodega y repartidores).
        $ciudad = $this->ciudadEnSeccional($ciudadObservacion, $seccional) ?? $this->ciudadEnSeccional($seccional->nombre, $seccional);
        $lugar = Texto::normalizar($seccional->nombre).($departamento ? ' / '.Texto::normalizar($departamento) : '');

        return [$ciudad, $this->alerta(
            self::ALERTA_CIUDAD,
            'la ciudad dice '.Texto::normalizar($ciudadOriginal).", pero la seccional y el departamento son {$lugar}.",
        )];
    }

    private function ciudadEnSeccional(?string $nombre, Seccional $seccional): ?Ciudad
    {
        if ($nombre === null) {
            return null;
        }

        return $this->ciudades->get(Texto::claveLugar($nombre), collect())->firstWhere('seccional_id', $seccional->id);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array{codigo: string, mensaje: string}>  $alertas
     * @return array{servicio: string|null, resultado: ResultadoFila, datos: array<string, mixed>, alertas: list<array{codigo: string, mensaje: string}>, zona_id: int|null, zona_origen: string|null}
     */
    private function resultado(?string $servicio, ResultadoFila $resultado, array $datos, array $alertas, ?int $zonaId = null, ?string $zonaOrigen = null): array
    {
        return [
            'servicio' => $servicio,
            'resultado' => $resultado,
            'datos' => $datos,
            'alertas' => $alertas,
            'zona_id' => $zonaId,
            'zona_origen' => $zonaOrigen,
        ];
    }

    /**
     * @return array{codigo: string, mensaje: string}
     */
    private function alerta(string $codigo, string $mensaje): array
    {
        return ['codigo' => $codigo, 'mensaje' => $mensaje];
    }

    /**
     * Texto de una celda. Los números enteros llegan como float (3937074.0) y se limpian.
     */
    private function cadena(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_float($valor) && floor($valor) === $valor) {
            $valor = (int) $valor;
        }

        $texto = trim((string) preg_replace('/\s+/u', ' ', (string) $valor));

        return $texto === '' || strtoupper($texto) === 'NONE' ? null : $texto;
    }

    private function entero(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_float($valor)) {
            return floor($valor) === $valor ? (int) $valor : null;
        }

        return is_string($valor) && ctype_digit(trim($valor)) ? (int) trim($valor) : null;
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $zona = (string) config('app.timezone');

        try {
            if (is_numeric($valor)) {
                return CarbonImmutable::instance(FechaExcel::excelToDateTimeObject((float) $valor, $zona))->setTimezone($zona);
            }

            foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y'] as $formato) {
                $fecha = CarbonImmutable::createFromFormat('!'.$formato, trim((string) $valor), $zona);

                if ($fecha !== null && $fecha->format($formato) === trim((string) $valor)) {
                    return $fecha;
                }
            }

            return CarbonImmutable::parse((string) $valor, $zona);
        } catch (Throwable) {
            return null;
        }
    }
}
