<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OperacionController;
use App\Http\Controllers\Api\V1\PerfilController;
use App\Http\Controllers\Api\V1\RutaController;
use App\Http\Controllers\Api\V1\VisitaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de la app del repartidor (v1)
|--------------------------------------------------------------------------
|
| Respuestas en JSON, errores con códigos HTTP estándar y límite de
| peticiones por usuario. Autenticación con token por dispositivo (Sanctum).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:ingreso')->name('login');

    Route::middleware(['auth:sanctum', 'usuario.activo', 'permission:app.usar', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('me', [PerfilController::class, 'show'])->name('me');
        Route::put('me/password', [PerfilController::class, 'cambiarPassword'])->name('me.password');
        Route::get('app/version', [PerfilController::class, 'version'])->name('app.version');

        Route::get('rutas/hoy', [RutaController::class, 'hoy'])->name('rutas.hoy');
        Route::get('rutas/historial', [RutaController::class, 'historial'])->name('rutas.historial');
        Route::put('rutas/{ruta}/orden', [RutaController::class, 'orden'])->name('rutas.orden');
        Route::post('rutas/{ruta}/iniciar', [RutaController::class, 'iniciar'])->name('rutas.iniciar');
        Route::get('rutas/{ruta}/cierre', [RutaController::class, 'cierre'])->name('rutas.cierre');
        Route::post('rutas/{ruta}/cerrar', [RutaController::class, 'cerrar'])->name('rutas.cerrar');

        Route::post('paradas/{parada}/visita', [VisitaController::class, 'store'])->middleware('throttle:visitas')->name('paradas.visita');

        Route::post('novedades', [OperacionController::class, 'novedad'])->name('novedades');
        Route::get('causales', [OperacionController::class, 'causales'])->name('causales');
        Route::post('dispositivos', [OperacionController::class, 'dispositivo'])->name('dispositivos');
    });
});
