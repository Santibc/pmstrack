<?php

use App\Models\Acceso;
use App\Services\Parametros;

it('rechaza una contraseña incorrecta y registra el intento', function () {
    $repartidor = repartidor(atributos: ['cedula' => '80123456']);

    $this->postJson('/api/v1/auth/login', ['usuario' => '80123456', 'password' => 'incorrecta'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('usuario');

    expect($repartidor->user->refresh()->intentos_fallidos)->toBe(1)
        ->and(Acceso::query()->where('exitoso', false)->where('canal', 'app')->count())->toBe(1);
});

it('bloquea al usuario tras superar los intentos fallidos', function () {
    $repartidor = repartidor(atributos: ['cedula' => '80123456']);
    $intentos = app(Parametros::class)->entero('bloqueo_intentos');

    foreach (range(1, $intentos) as $intento) {
        $this->postJson('/api/v1/auth/login', ['usuario' => '80123456', 'password' => 'incorrecta'])->assertUnprocessable();
    }

    expect($repartidor->user->refresh()->estaBloqueado())->toBeTrue();

    // Con la contraseña correcta tampoco entra mientras dure el bloqueo.
    $this->postJson('/api/v1/auth/login', ['usuario' => '80123456', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.usuario.0', fn (string $mensaje) => str_contains($mensaje, 'bloqueado'));

    $this->travel(app(Parametros::class)->entero('bloqueo_minutos') + 1)->minutes();

    $this->postJson('/api/v1/auth/login', ['usuario' => '80123456', 'password' => 'password'])->assertOk();
});

it('no deja entrar a la app a quien no es repartidor', function () {
    $admin = administradora();

    $this->postJson('/api/v1/auth/login', ['usuario' => $admin->email, 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.usuario.0', fn (string $mensaje) => str_contains($mensaje, 'repartidores'));
});

it('no deja entrar a un repartidor desactivado', function () {
    $repartidor = repartidor(atributos: ['cedula' => '80123456']);
    $repartidor->user->update(['activo' => false]);

    $this->postJson('/api/v1/auth/login', ['usuario' => '80123456', 'password' => 'password'])->assertUnprocessable();
});

it('exige el token en los servicios de la app', function () {
    $this->getJson('/api/v1/rutas/hoy')->assertUnauthorized();
});

it('devuelve ruta vacía cuando el repartidor no tiene planeación', function () {
    $token = repartidor()->user->createToken('prueba')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/rutas/hoy')->assertOk()->assertJsonPath('data', null);
});

it('entrega el catálogo de causales por tipo', function () {
    $token = repartidor()->user->createToken('prueba')->plainTextToken;

    $causales = $this->withToken($token)->getJson('/api/v1/causales')->assertOk()->json('data');

    expect(collect($causales)->pluck('tipo')->unique()->sort()->values()->all())->toBe(['anulado', 'no_entregado']);
});

it('cierra la sesión revocando el token del dispositivo', function () {
    $usuario = repartidor()->user;
    $token = $usuario->createToken('prueba')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($usuario->tokens()->count())->toBe(0);
});

it('responde con un error de validación cuando el usuario no existe', function () {
    $this->postJson('/api/v1/auth/login', ['usuario' => '99999999', 'password' => 'cualquiera'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.usuario.0', 'El usuario o la contraseña no son correctos.');

    $this->postJson('/api/v1/auth/login', ['usuario' => 'nadie@pmstrack.test', 'password' => 'cualquiera'])
        ->assertUnprocessable();
});
