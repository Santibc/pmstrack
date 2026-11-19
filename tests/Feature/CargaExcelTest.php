<?php

use App\Enums\EstadoCarga;
use App\Enums\EstadoGuia;
use App\Enums\ResultadoFila;
use App\Enums\TipoEvento;
use App\Exceptions\ArchivoInvalido;
use App\Exceptions\OperacionNoPermitida;
use App\Models\Ciudad;
use App\Models\Direccion;
use App\Models\Guia;
use App\Services\Cargas\AnalizadorFilas;
use App\Services\Cargas\CargaService;
use App\Services\Zonificacion\ZonificacionService;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ArchivoCredibanco;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = administradora();
    $this->cargas = app(CargaService::class);
});

function cargar(array $filas, ?array $encabezados = null)
{
    return app(CargaService::class)->procesarRuta(
        ArchivoCredibanco::crear($filas, $encabezados),
        'Entrega_diaria_papeleria_23-10-2026.xlsx',
        test()->admin,
    );
}

it('rechaza un archivo al que le falta una columna obligatoria', function () {
    $encabezados = array_values(array_diff(ArchivoCredibanco::ENCABEZADOS, ['PAPELERIA_ENTREGAR']));

    cargar([ArchivoCredibanco::fila()], $encabezados);
})->throws(ArchivoInvalido::class, 'PAPELERIA_ENTREGAR');

it('rechaza un archivo que no es el Excel de Credibanco', function () {
    cargar([['A' => 1]], ['A', 'B', 'C']);
})->throws(ArchivoInvalido::class, 'encabezados');

it('deja la carga en revisión con el resumen de la validación', function () {
    $carga = cargar([
        ArchivoCredibanco::fila(),
        ArchivoCredibanco::fila(['HORARIO_DE_ATENCION' => 'Lunes a Viernes 8:30 - 17:30 - 8 horas', 'PAPELERIA_ENTREGAR' => 74]),
    ]);

    expect($carga->estado)->toBe(EstadoCarga::Pendiente)
        ->and($carga->columnas_reconocidas)->toBe(16)
        ->and($carga->total_filas)->toBe(2)
        ->and($carga->nuevos)->toBe(2)
        ->and($carga->urgentes)->toBe(1)
        ->and($carga->total_rollos)->toBe(97)
        ->and($carga->por_zonificar)->toBe(2)
        ->and(Guia::query()->count())->toBe(0);

    Storage::disk('local')->assertExists($carga->ruta_archivo);
});

it('crea las guías al confirmar, con ANS, contacto y bitácora', function () {
    $carga = cargar([ArchivoCredibanco::fila(['SERVICIO' => 3937452, 'CODIGO_UNICO' => 18452276])]);

    $this->cargas->confirmar($carga, $this->admin);

    $guia = Guia::query()->where('servicio', '3937452')->firstOrFail();

    expect($carga->refresh()->estado)->toBe(EstadoCarga::Confirmada)
        ->and($guia->estado)->toBe(EstadoGuia::PorZonificar)
        ->and($guia->numero_guia)->toMatch('/^[A-Z]{3}-D-\d{4,}$/')
        ->and($guia->direccion_normalizada)->toBe('Calle 116 #18b-09 local 2')
        ->and($guia->direccion->direccion)->toBe('Calle 116 #18b-09 local 2')
        ->and($guia->direccion->clave_normalizada)->toBe('CL116#18B-9')
        ->and($guia->ans_horas)->toBe(168)
        ->and($guia->fecha_registro->format('Y-m-d H:i'))->toBe('2026-10-22 16:12')
        ->and($guia->ans_vence_at->format('Y-m-d H:i'))->toBe('2026-10-29 16:12')
        ->and($guia->es_urgente)->toBeFalse()
        ->and($guia->contacto_nombre)->toBe('Rosa Castellanos')
        ->and($guia->contacto_telefono)->toBe('3215550187')
        ->and($guia->horario_comercio)->toBe('8am a 4pm')
        ->and($guia->comercio->codigo_unico)->toBe('18452276')
        ->and($guia->eventos->pluck('tipo')->all())->toBe([TipoEvento::SolicitudRegistrada, TipoEvento::Cargada]);
});

it('omite los servicios duplicados, tanto los ya cargados como los repetidos en el archivo', function () {
    $this->cargas->confirmar(cargar([ArchivoCredibanco::fila(['SERVICIO' => 3937477])]), $this->admin);

    $carga = cargar([
        ArchivoCredibanco::fila(['SERVICIO' => 3937477]),
        ArchivoCredibanco::fila(['SERVICIO' => 3937999]),
        ArchivoCredibanco::fila(['SERVICIO' => 3937999]),
    ]);

    expect($carga->nuevos)->toBe(1)
        ->and($carga->duplicados)->toBe(2)
        ->and($carga->filas->pluck('resultado')->all())->toBe([ResultadoFila::Duplicado, ResultadoFila::Nuevo, ResultadoFila::Duplicado]);

    $this->cargas->confirmar($carga, $this->admin);

    expect(Guia::query()->count())->toBe(2);
});

it('marca las alertas de datos sin impedir la carga', function () {
    $carga = cargar([
        // Ciudad de otra seccional.
        ArchivoCredibanco::fila(['CIUDAD' => 'BOGOTA', 'SECCIONAL' => 'MEDELLIN', 'DEPARTAMENTO' => 'ANTIOQUIA', 'DIRECCION' => 'Cra25A#1A sur 45']),
        // Dirección rural.
        ArchivoCredibanco::fila(['DIRECCION' => 'Km1 via Mosquera la mesa']),
        // Teléfono de relleno y sin contacto en observaciones.
        ArchivoCredibanco::fila(['TELEFONO' => '+57 1234567', 'OBSERVACIONES' => 'BUEN DIA COMERCIO REQUIERE 20 ROLLOS', 'PAPELERIA_ENTREGAR' => 15]),
    ]);

    $alertas = $carga->filas->map(fn ($fila) => collect($fila->alertas)->pluck('codigo')->all());

    expect($carga->con_alertas)->toBe(3)
        ->and($alertas[0])->toContain(AnalizadorFilas::ALERTA_CIUDAD)
        ->and($alertas[1])->toContain(AnalizadorFilas::ALERTA_RURAL)
        ->and($alertas[2])->toContain(AnalizadorFilas::ALERTA_TELEFONO, AnalizadorFilas::ALERTA_ROLLOS);

    $this->cargas->confirmar($carga, $this->admin);

    // Manda la seccional: la guía queda en Medellín aunque la columna diga Bogotá.
    $guia = Guia::query()->where('direccion_original', 'Cra25A#1A sur 45')->firstOrFail();

    expect($guia->seccional->codigo)->toBe('MED')
        ->and($guia->ciudad->nombre)->toBe('Medellín');
});

it('no carga las filas con errores y lo explica', function () {
    $carga = cargar([
        ArchivoCredibanco::fila(['PAPELERIA_ENTREGAR' => 'muchos']),
        ArchivoCredibanco::fila(['SECCIONAL' => 'BARRANQUILLA']),
        ArchivoCredibanco::fila(),
    ]);

    expect($carga->errores)->toBe(2)
        ->and($carga->nuevos)->toBe(1)
        ->and($carga->filas[0]->alertas[0]['mensaje'])->toContain('PAPELERIA_ENTREGAR')
        ->and($carga->filas[1]->alertas[0]['mensaje'])->toContain('BARRANQUILLA');
});

it('crea la ciudad cuando no está en el catálogo', function () {
    $carga = cargar([ArchivoCredibanco::fila(['CIUDAD' => 'TABIO', 'DEPARTAMENTO' => 'CUNDINAMARCA', 'DIRECCION' => 'Calle 4 # 3-20'])]);

    $this->cargas->confirmar($carga, $this->admin);

    expect(Ciudad::query()->where('nombre_normalizado', 'TABIO')->first())
        ->seccional_id->toBe(seccional('BOG')->id)
        ->nombre->toBe('Tabio');
});

it('asigna la zona sola cuando la dirección ya es conocida', function () {
    $this->cargas->confirmar(cargar([ArchivoCredibanco::fila(['DIRECCION' => 'Cr7 # 61- 48', 'CODIGO_UNICO' => 11111111])]), $this->admin);

    $direccion = Direccion::query()->where('clave_normalizada', 'KR7#61-48')->firstOrFail();
    app(ZonificacionService::class)->asignarZona($direccion, zona('Z02'), $this->admin);

    // La misma dirección escrita de otra forma y de otro comercio.
    $carga = cargar([ArchivoCredibanco::fila(['DIRECCION' => 'CR 7 61 48, BOGOTA', 'CODIGO_UNICO' => 22222222])]);

    expect($carga->con_zona)->toBe(1)->and($carga->por_zonificar)->toBe(0);

    $this->cargas->confirmar($carga, $this->admin);
    $guia = Guia::query()->latest('id')->firstOrFail();

    expect($guia->estado)->toBe(EstadoGuia::ListaParaAsignar)
        ->and($guia->zona->codigo)->toBe('Z02')
        ->and($guia->eventos->last()->tipo)->toBe(TipoEvento::ZonaAutomatica);
});

it('asigna la zona sola por el código único del comercio aunque cambie la dirección', function () {
    $this->cargas->confirmar(cargar([ArchivoCredibanco::fila(['DIRECCION' => 'Calle 12 # 1-40', 'CODIGO_UNICO' => 20577413])]), $this->admin);

    app(ZonificacionService::class)->asignarZona(Direccion::query()->firstOrFail(), zona('Z03'), $this->admin);

    $carga = cargar([ArchivoCredibanco::fila(['DIRECCION' => 'Carrera 4 # 12-30 piso 2', 'CODIGO_UNICO' => 20577413])]);

    expect($carga->filas->first()->zona_origen)->toBe(ZonificacionService::ORIGEN_COMERCIO)
        ->and($carga->filas->first()->zona_id)->toBe(zona('Z03')->id);
});

it('al zonificar una dirección libera todas las guías que la esperaban', function () {
    $this->cargas->confirmar(cargar([
        ArchivoCredibanco::fila(['DIRECCION' => 'Cra 68 # 22-41']),
        ArchivoCredibanco::fila(['DIRECCION' => 'CRA 68 22 41 LOCAL 3']),
    ]), $this->admin);

    expect(app(ZonificacionService::class)->totalPendientes(seccional()->id))->toBe(1);

    $liberadas = app(ZonificacionService::class)->asignarZona(Direccion::query()->firstOrFail(), zona('Z04'), $this->admin);

    expect($liberadas)->toBe(2)
        ->and(Guia::query()->where('estado', EstadoGuia::ListaParaAsignar)->where('zona_id', zona('Z04')->id)->count())->toBe(2)
        ->and(app(ZonificacionService::class)->totalPendientes(seccional()->id))->toBe(0);
});

it('no permite confirmar dos veces la misma carga', function () {
    $carga = cargar([ArchivoCredibanco::fila()]);
    $this->cargas->confirmar($carga, $this->admin);

    $this->cargas->confirmar($carga, $this->admin);
})->throws(OperacionNoPermitida::class);
