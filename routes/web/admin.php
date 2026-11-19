<?php

use App\Http\Controllers\ExportController;
use App\Livewire\Admin\Cargas\Cargar;
use App\Livewire\Admin\Configuracion\Index as Configuracion;
use App\Livewire\Admin\Inventario\Index as Inventario;
use App\Livewire\Admin\Planeacion\Index as Planeacion;
use App\Livewire\Admin\Repartidores\Index as Repartidores;
use App\Livewire\Admin\Seguimiento\Index as Seguimiento;
use App\Livewire\Admin\Zonificacion\Index as Zonificacion;
use App\Livewire\CalidadDeDatos;
use App\Livewire\Guias\Detalle;
use App\Livewire\Guias\Listado;
use App\Livewire\Reportes\Index as Reportes;
use App\Livewire\Tablero;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plataforma de administración de PMS
|--------------------------------------------------------------------------
|
| Cada pantalla exige su permiso; el coordinador de seccional además queda
| limitado a los datos de su seccional dentro de cada componente.
|
*/

Route::livewire('/', Tablero::class)->middleware('permission:dashboard.ver')->name('dashboard');

Route::livewire('/cargas', Cargar::class)->middleware('permission:cargas.gestionar')->name('cargas');
Route::livewire('/zonificacion', Zonificacion::class)->middleware('permission:zonificacion.gestionar')->name('zonificacion');
Route::livewire('/planeacion', Planeacion::class)->middleware('permission:planeacion.gestionar')->name('planeacion');
Route::livewire('/seguimiento', Seguimiento::class)->middleware('permission:seguimiento.ver')->name('seguimiento');
Route::livewire('/repartidores', Repartidores::class)->middleware('permission:repartidores.gestionar')->name('repartidores');
Route::livewire('/configuracion', Configuracion::class)->middleware('permission:configuracion.gestionar')->name('configuracion');

Route::middleware('permission:inventario.ver')->group(function () {
    Route::livewire('/inventario', Inventario::class)->name('inventario');
    Route::get('/inventario/exportar', [ExportController::class, 'inventario'])->name('inventario.exportar');
});

Route::middleware('permission:guias.ver')->group(function () {
    Route::livewire('/guias', Listado::class)->name('guias');
    Route::get('/guias/exportar', [ExportController::class, 'guias'])->name('guias.exportar');
    Route::livewire('/guias/{guia}', Detalle::class)->name('guias.show');
    Route::get('/guias/{guia}/comprobante', [ExportController::class, 'comprobante'])->name('guias.comprobante');
});

Route::middleware('permission:reportes.ver')->group(function () {
    Route::livewire('/reportes', Reportes::class)->name('reportes');
    Route::get('/reportes/{tipo}/exportar', [ExportController::class, 'reporte'])->name('reportes.exportar');
    Route::livewire('/calidad-de-datos', CalidadDeDatos::class)->name('calidad');
    Route::get('/calidad-de-datos/exportar', [ExportController::class, 'calidad'])->name('calidad.exportar');
});
