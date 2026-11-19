<?php

use App\Http\Controllers\ExportController;
use App\Livewire\CalidadDeDatos;
use App\Livewire\Guias\Detalle;
use App\Livewire\Guias\Listado;
use App\Livewire\Portal\ConsultaRapida;
use App\Livewire\Reportes\Index as Reportes;
use App\Livewire\Tablero;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal Credibanco (solo lectura)
|--------------------------------------------------------------------------
|
| Dashboard, histórico, evidencia completa y reportes. Usa las mismas
| pantallas de consulta que PMS, sin las acciones de operación.
|
*/

Route::livewire('/', Tablero::class)->name('dashboard');

Route::livewire('/servicios', Listado::class)->name('guias');
Route::get('/servicios/exportar', [ExportController::class, 'guias'])->name('guias.exportar');
Route::livewire('/servicios/{guia}', Detalle::class)->name('guias.show');
Route::get('/servicios/{guia}/comprobante', [ExportController::class, 'comprobante'])->name('guias.comprobante');

Route::livewire('/consulta', ConsultaRapida::class)->name('consulta');

Route::livewire('/reportes', Reportes::class)->name('reportes');
Route::get('/reportes/{tipo}/exportar', [ExportController::class, 'reporte'])->name('reportes.exportar');

Route::livewire('/calidad-de-datos', CalidadDeDatos::class)->name('calidad');
Route::get('/calidad-de-datos/exportar', [ExportController::class, 'calidad'])->name('calidad.exportar');
