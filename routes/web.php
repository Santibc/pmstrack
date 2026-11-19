<?php

use App\Enums\Rol;
use App\Http\Controllers\SesionController;
use App\Livewire\Auth\Ingresar;
use App\Livewire\Auth\OlvideClave;
use App\Livewire\Auth\RestablecerClave;
use App\Livewire\Cuenta;
use App\Livewire\Publico\Consulta;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Público
|--------------------------------------------------------------------------
*/

Route::livewire('/', Consulta::class)->name('consulta');
Route::view('/privacidad', 'privacidad')->name('privacidad');

Route::middleware('guest')->group(function () {
    Route::livewire('/ingresar', Ingresar::class)->name('login');
    Route::livewire('/recuperar-clave', OlvideClave::class)->name('password.request');
    Route::livewire('/restablecer-clave/{token}', RestablecerClave::class)->name('password.reset');
});

/*
|--------------------------------------------------------------------------
| Con sesión
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'usuario.activo'])->group(function () {
    Route::post('/salir', [SesionController::class, 'destroy'])->name('logout');
    Route::livewire('/cuenta', Cuenta::class)->name('cuenta');

    require __DIR__.'/web/evidencias.php';

    // Plataforma de administración de PMS.
    Route::prefix('admin')->name('admin.')
        ->middleware('role:'.implode('|', Rol::administracion()))
        ->group(__DIR__.'/web/admin.php');

    // Portal Credibanco (solo lectura).
    Route::prefix('portal')->name('portal.')
        ->middleware('permission:portal.ver')
        ->group(__DIR__.'/web/portal.php');
});
