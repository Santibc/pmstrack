<?php

use App\Enums\EstadoGuia;
use App\Enums\EstadoParada;
use App\Enums\EstadoRuta;
use App\Enums\TipoEvidencia;
use App\Enums\TipoMovimiento;
use App\Exceptions\OperacionNoPermitida;
use App\Models\Causal;
use App\Models\Ciudad;
use App\Models\Direccion;
use App\Models\Guia;
use App\Models\MovimientoInventario;
use App\Models\OrdenMemoria;
use App\Models\Ruta;
use App\Models\Visita;
use App\Services\Inventario\InventarioService;
use App\Services\Planeacion\PlaneacionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    Http::fake();

    $this->admin = administradora();
    $this->repartidor = repartidor('Carlos Rodríguez', atributos: ['cedula' => '80123456']);
    $this->zona = zona('Z01');
    $this->planeacion = app(PlaneacionService::class);

    app(InventarioService::class)->entrada(bodega(), 1000, 'R-10458', today(), $this->admin);
});

/**
 * Crea guías listas para asignar en la zona Z01 y publica la planeación de hoy.
 *
 * @param  list<int>  $rollos
 */
function publicarRuta(array $rollos = [23, 8, 40]): Ruta
{
    $ciudad = Ciudad::query()->where('nombre_normalizado', 'BOGOTA')->firstOrFail();

    foreach ($rollos as $cantidad) {
        Guia::factory()->enZona(test()->zona)->create([
            'direccion_id' => Direccion::factory()->create(['ciudad_id' => $ciudad->id, 'zona_id' => test()->zona->id]),
            'rollos_solicitados' => $cantidad,
        ]);
    }

    $planeacion = test()->planeacion->obtener(seccional(), today());
    test()->planeacion->asignarZona($planeacion, test()->zona, test()->repartidor);
    test()->planeacion->publicar($planeacion, test()->admin);

    return Ruta::query()->where('repartidor_id', test()->repartidor->id)->firstOrFail();
}

function entrega(array $cambios = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'resultado' => 'entregado',
        'registrada_at' => now()->toIso8601String(),
        'rollos_entregados' => 23,
        'recibe_nombre' => 'Mónica Herrera',
        'recibe_cedula' => '1.020.456.789',
        'recibe_telefono' => '310 555 0142',
        'autoriza_datos' => '1',
        'latitud' => 4.6985,
        'longitud' => -74.0412,
        'precision' => 6,
        'foto' => UploadedFile::fake()->image('foto.jpg', 1200, 900),
        'firma' => UploadedFile::fake()->image('firma.png', 600, 240),
    ], $cambios);
}

it('al publicar crea la ruta, descuenta el inventario y deja la guía asignada', function () {
    $ruta = publicarRuta();

    expect($ruta->estado)->toBe(EstadoRuta::Asignada)
        ->and($ruta->paradas()->count())->toBe(3)
        ->and($ruta->rollos_salida)->toBe(71)
        ->and(bodega()->saldo)->toBe(929)
        ->and(Guia::query()->where('estado', EstadoGuia::Asignada)->where('repartidor_id', $this->repartidor->id)->count())->toBe(3);

    $salida = MovimientoInventario::query()->where('tipo', TipoMovimiento::SalidaRuta)->firstOrFail();

    expect($salida->cantidad)->toBe(-71)->and($salida->saldo)->toBe(929);
});

it('no vuelve a sacar las mismas guías si se publica dos veces', function () {
    publicarRuta();

    $planeacion = $this->planeacion->obtener(seccional(), today());

    expect(fn () => $this->planeacion->publicar($planeacion, $this->admin))
        ->toThrow(OperacionNoPermitida::class);

    expect(bodega()->saldo)->toBe(929);
});

it('el repartidor entra con su cédula y recibe su ruta del día', function () {
    publicarRuta();

    $login = $this->postJson('/api/v1/auth/login', [
        'usuario' => '80123456',
        'password' => 'password',
        'dispositivo' => ['modelo' => 'Moto G54', 'plataforma' => 'android', 'app_version' => '1.0.0'],
    ])->assertOk()->assertJsonPath('usuario.repartidor.cedula', '80123456');

    $this->withToken($login->json('token'))
        ->getJson('/api/v1/rutas/hoy')
        ->assertOk()
        ->assertJsonPath('data.estado', 'asignada')
        ->assertJsonPath('data.resumen.guias', 3)
        ->assertJsonPath('data.resumen.rollos', 71)
        ->assertJsonPath('data.resumen.nuevas', 3)
        ->assertJsonCount(3, 'data.paradas');
});

it('recorre la ruta completa: orden, inicio, tres resultados y cierre con cuadre', function () {
    $ruta = publicarRuta([23, 8, 40]);
    Sanctum::actingAs($this->repartidor->user, ['app']);

    // Las guías se crean en el orden de los rollos: 23, 8 y 40.
    [$primera, $segunda, $tercera] = $ruta->paradas()->with('guia')->orderBy('guia_id')->get()->all();

    // Organiza y guarda la ruta en otro orden.
    $this->putJson("/api/v1/rutas/{$ruta->id}/orden", ['paradas' => [$tercera->id, $primera->id, $segunda->id]])
        ->assertOk()
        ->assertJsonPath('data.resumen.nuevas', 0)
        ->assertJsonPath('data.paradas.0.id', $tercera->id);

    expect(OrdenMemoria::query()->where('repartidor_id', $this->repartidor->id)->count())->toBe(3);

    $this->postJson("/api/v1/rutas/{$ruta->id}/iniciar")->assertOk()->assertJsonPath('data.estado', 'en_curso');
    expect($primera->guia->refresh()->estado)->toBe(EstadoGuia::EnReparto);

    // Entregado con evidencia completa.
    $this->post("/api/v1/paradas/{$primera->id}/visita", entrega(), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.resultado', 'entregado')
        ->assertJsonCount(2, 'data.evidencias');

    $guia = $primera->guia->refresh();
    $visita = Visita::query()->where('parada_id', $primera->id)->firstOrFail();

    expect($guia->estado)->toBe(EstadoGuia::Entregada)
        ->and($guia->rollos_entregados)->toBe(23)
        ->and($guia->intentos)->toBe(1)
        ->and($visita->recibe_cedula)->toBe('1020456789')
        ->and($visita->recibeNombreEnmascarado())->toBe('Mónica H***')
        ->and($visita->recibeCedulaEnmascarada())->toBe('****6789')
        ->and($visita->evidencia(TipoEvidencia::Foto)->integridadVerificada())->toBeTrue()
        ->and($visita->evidencia(TipoEvidencia::Firma))->not->toBeNull();

    // No entregado: se reprograma y suma un intento.
    $causal = Causal::query()->where('codigo', 'local_cerrado')->firstOrFail();

    $this->postJson("/api/v1/paradas/{$segunda->id}/visita", [
        'uuid' => (string) Str::uuid(),
        'resultado' => 'no_entregado',
        'registrada_at' => now()->toIso8601String(),
        'causal_id' => $causal->id,
        'observacion' => 'Reja abajo. El vecino informa que abren a las 2 de la tarde.',
    ])->assertCreated();

    $reprogramada = $segunda->guia->refresh();

    expect($reprogramada->estado)->toBe(EstadoGuia::NoEntregada)
        ->and($reprogramada->intentos)->toBe(1)
        ->and($reprogramada->fecha_programada->isAfter(today()))->toBeTrue();

    // Anulado: estado final.
    $this->postJson("/api/v1/paradas/{$tercera->id}/visita", [
        'uuid' => (string) Str::uuid(),
        'resultado' => 'anulado',
        'registrada_at' => now()->toIso8601String(),
        'causal_id' => Causal::query()->where('codigo', 'direccion_errada')->value('id'),
        'observacion' => 'La dirección corresponde a un lote vacío.',
    ])->assertCreated();

    expect($tercera->guia->refresh()->estado)->toBe(EstadoGuia::Anulada);

    // Cierre: debe devolver los rollos de la no entregada (8) y la anulada (40).
    $this->getJson("/api/v1/rutas/{$ruta->id}/cierre")
        ->assertOk()
        ->assertJsonPath('data.rollos_salida', 71)
        ->assertJsonPath('data.rollos_entregados', 23)
        ->assertJsonPath('data.rollos_por_devolver', 48);

    $this->postJson("/api/v1/rutas/{$ruta->id}/cerrar", ['rollos_devueltos' => 48])
        ->assertOk()
        ->assertJsonPath('data.estado', 'cerrada')
        ->assertJsonPath('data.cierre.cuadre_ok', true);

    // Inventario: 1000 − 71 + 48.
    expect(bodega()->saldo)->toBe(977)
        ->and(MovimientoInventario::query()->where('tipo', TipoMovimiento::Devolucion)->value('cantidad'))->toBe(48);
});

it('no duplica la visita cuando la app reenvía el mismo registro', function () {
    $ruta = publicarRuta([23]);
    Sanctum::actingAs($this->repartidor->user, ['app']);
    $parada = $ruta->paradas()->firstOrFail();
    $datos = entrega();

    $this->post("/api/v1/paradas/{$parada->id}/visita", $datos, ['Accept' => 'application/json'])->assertCreated();
    $this->post("/api/v1/paradas/{$parada->id}/visita", $datos, ['Accept' => 'application/json'])->assertOk();

    expect(Visita::query()->count())->toBe(1);
});

it('inicia la ruta sola si la visita guardada sin señal llega antes que el inicio', function () {
    $ruta = publicarRuta([23]);
    Sanctum::actingAs($this->repartidor->user, ['app']);

    $this->post('/api/v1/paradas/'.$ruta->paradas()->value('id').'/visita', entrega(), ['Accept' => 'application/json'])->assertCreated();

    expect($ruta->refresh()->estado)->toBe(EstadoRuta::EnCurso);
});

it('exige la evidencia completa para una entrega', function (array $cambios, string $mensaje) {
    $ruta = publicarRuta([23]);
    Sanctum::actingAs($this->repartidor->user, ['app']);

    $this->post('/api/v1/paradas/'.$ruta->paradas()->value('id').'/visita', entrega($cambios), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => $mensaje]);

    expect(Visita::query()->count())->toBe(0);
})->with([
    'sin foto' => [['foto' => null], 'La entrega necesita al menos una foto.'],
    'sin firma' => [['firma' => null], 'La entrega necesita la firma de quien recibe.'],
    'sin cédula' => [['recibe_cedula' => null], 'Falta la cédula de quien recibe.'],
    'sin autorización de datos' => [['autoriza_datos' => '0'], 'Quien recibe debe autorizar el tratamiento de sus datos personales.'],
    'más rollos de los solicitados' => [['rollos_entregados' => 24], 'No se pueden entregar más rollos de los solicitados (23).'],
]);

it('registra una entrega parcial y espera la diferencia en el cierre', function () {
    $ruta = publicarRuta([30]);
    Sanctum::actingAs($this->repartidor->user, ['app']);

    $this->post('/api/v1/paradas/'.$ruta->paradas()->value('id').'/visita', entrega(['rollos_entregados' => 24]), ['Accept' => 'application/json'])->assertCreated();

    $this->postJson("/api/v1/rutas/{$ruta->id}/cerrar", ['rollos_devueltos' => 4])
        ->assertOk()
        ->assertJsonPath('data.cierre.rollos_devueltos_esperados', 6)
        ->assertJsonPath('data.cierre.cuadre_ok', false);
});

it('al cerrar con paradas sin visitar las devuelve a planeación y explica sus rollos', function () {
    $ruta = publicarRuta([23, 8, 40]);
    Sanctum::actingAs($this->repartidor->user, ['app']);
    [$visitada, $otra, $ultima] = $ruta->paradas()->orderBy('guia_id')->get()->all();

    $this->post("/api/v1/paradas/{$visitada->id}/visita", entrega(), ['Accept' => 'application/json'])->assertCreated();
    $this->postJson("/api/v1/rutas/{$ruta->id}/cerrar", ['rollos_devueltos' => 48])
        ->assertOk()
        ->assertJsonPath('data.cierre.cuadre_ok', true);

    expect($otra->refresh()->estado)->toBe(EstadoParada::SinVisitar)
        ->and(Guia::query()->findOrFail($otra->guia_id)->estado)->toBe(EstadoGuia::ListaParaAsignar);

    // El resumen de la ruta ya cerrada sigue explicando de dónde salen los 48 rollos.
    $this->getJson("/api/v1/rutas/{$ruta->id}/cierre")
        ->assertOk()
        ->assertJsonPath('data.rollos_por_devolver', 48)
        ->assertJsonCount(2, 'data.detalle')
        ->assertJsonPath('data.detalle.0.motivo', 'sin visitar')
        ->assertJsonPath('data.detalle.1.motivo', 'sin visitar');
});

it('un repartidor no puede operar la ruta de otro', function () {
    $ruta = publicarRuta([23]);
    Sanctum::actingAs(repartidor('Andrés Gómez')->user, ['app']);

    $this->postJson("/api/v1/rutas/{$ruta->id}/iniciar")->assertForbidden();
    $this->post('/api/v1/paradas/'.$ruta->paradas()->value('id').'/visita', entrega(), ['Accept' => 'application/json'])->assertForbidden();
});

it('recuerda el orden por zona para la siguiente ruta', function () {
    $ruta = publicarRuta([23, 8]);
    Sanctum::actingAs($this->repartidor->user, ['app']);
    [$a, $b] = $ruta->paradas()->with('guia')->orderBy('guia_id')->get()->all();

    $this->putJson("/api/v1/rutas/{$ruta->id}/orden", ['paradas' => [$b->id, $a->id]])->assertOk();
    $this->postJson("/api/v1/rutas/{$ruta->id}/cerrar", ['rollos_devueltos' => 31])->assertOk();

    // Al día siguiente llegan las mismas dos direcciones más una nueva.
    $this->travelTo(now()->addDay()->setTime(7, 0));

    foreach ([$a, $b] as $parada) {
        Guia::factory()->enZona($this->zona)->create(['direccion_id' => $parada->guia->direccion_id, 'rollos_solicitados' => 10]);
    }
    Guia::factory()->enZona($this->zona)->create([
        'direccion_id' => Direccion::factory()->create(['ciudad_id' => $a->guia->ciudad_id, 'zona_id' => $this->zona->id]),
        'rollos_solicitados' => 10,
    ]);

    // Las dos que quedaron sin visitar vuelven a planeación junto con las nuevas.
    $planeacion = $this->planeacion->obtener(seccional(), today());
    $this->planeacion->asignarZona($planeacion, $this->zona, $this->repartidor);
    $this->planeacion->publicar($planeacion, $this->admin);

    $manana = Ruta::query()->whereDate('fecha', today())->firstOrFail();
    $paradas = $manana->paradas()->enOrden()->with('guia')->get();

    expect($paradas)->toHaveCount(5)
        ->and($paradas->where('es_nueva', true))->toHaveCount(1)
        ->and($paradas->first()->es_nueva)->toBeTrue()
        // Las conocidas conservan el orden guardado: primero la dirección de B, luego la de A.
        ->and($paradas->where('es_nueva', false)->pluck('guia.direccion_id')->unique()->values()->all())
        ->toBe([$b->guia->direccion_id, $a->guia->direccion_id]);
});

it('conserva el orden del repartidor y la prioridad al publicar más guías el mismo día', function () {
    $ruta = publicarRuta([23, 8, 40]);
    Sanctum::actingAs($this->repartidor->user, ['app']);
    [$a, $b, $c] = $ruta->paradas()->with('guia')->orderBy('guia_id')->get()->all();

    $this->putJson("/api/v1/rutas/{$ruta->id}/orden", ['paradas' => [$c->id, $a->id, $b->id]])->assertOk();
    $this->planeacion->priorizarParada($b->refresh(), $this->admin);

    // Llegan dos guías más: una a la misma dirección de A (el repartidor ya la conoce) y una dirección nueva.
    Guia::factory()->enZona($this->zona)->create(['direccion_id' => $a->guia->direccion_id, 'rollos_solicitados' => 5]);
    Guia::factory()->enZona($this->zona)->create([
        'direccion_id' => Direccion::factory()->create(['ciudad_id' => $a->guia->ciudad_id, 'zona_id' => $this->zona->id]),
        'rollos_solicitados' => 7,
    ]);

    $this->planeacion->publicar($this->planeacion->obtener(seccional(), today()), $this->admin);

    $paradas = $ruta->paradas()->enOrden()->with('guia')->get();
    $ordenadas = $paradas->where('es_nueva', false)->values();

    expect($paradas)->toHaveCount(5)
        ->and($paradas->where('es_nueva', true))->toHaveCount(1)
        // La priorizada sigue primera, se respeta el orden del repartidor y la conocida va junto a A.
        ->and($ordenadas->pluck('id')->take(3)->all())->toBe([$b->id, $c->id, $a->id])
        ->and($ordenadas->last()->guia->direccion_id)->toBe($a->guia->direccion_id)
        ->and($ordenadas->pluck('orden')->all())->toBe([1, 2, 3, 4]);
});

it('reasigna una parada pendiente a otro repartidor sin mover el inventario', function () {
    $ruta = publicarRuta([23, 8]);
    $otro = repartidor('Andrés Gómez');
    $parada = $ruta->paradas()->orderBy('guia_id')->firstOrFail();

    $nueva = $this->planeacion->reasignarParada($parada, $otro, $this->admin);

    expect($parada->refresh()->estado)->toBe(EstadoParada::Reasignada)
        ->and($nueva->ruta->repartidor_id)->toBe($otro->id)
        ->and($ruta->refresh()->rollos_salida)->toBe(8)
        ->and($nueva->ruta->rollos_salida)->toBe(23)
        ->and($parada->guia->refresh()->repartidor_id)->toBe($otro->id)
        ->and(bodega()->saldo)->toBe(969);
});
