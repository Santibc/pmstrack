<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\EvidenciaController;
use Illuminate\Support\Facades\Route;

// Fotos y firmas: solo para usuarios autorizados o por enlace temporal firmado.
Route::get('/evidencias/{evidencia}', [EvidenciaController::class, 'show'])->name('evidencias.show');

Route::get('/evidencias/{evidencia}/firmada', [EvidenciaController::class, 'show'])
    ->middleware('signed')
    ->withoutMiddleware(['auth', 'usuario.activo'])
    ->name('evidencias.firmada');

// Enlace privado de descarga del APK de la app del repartidor.
Route::get('/app/descargar', [AppController::class, 'descargar'])->name('app.descargar');
