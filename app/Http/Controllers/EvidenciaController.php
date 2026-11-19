<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenciaController extends Controller
{
    /**
     * Entrega una foto o firma desde el disco privado.
     *
     * La ruta normal exige sesión y permiso; la ruta firmada exige una firma
     * vigente (enlace temporal). La evidencia nunca se sirve desde /public.
     */
    public function show(Request $request, Evidencia $evidencia): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort_unless($request->user()?->can('ver', $evidencia) ?? false, 403);
        }

        abort_unless($evidencia->existe(), 404);

        return Storage::disk($evidencia->disk)->response($evidencia->ruta, null, [
            'Content-Type' => $evidencia->mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
