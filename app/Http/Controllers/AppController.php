<?php

namespace App\Http\Controllers;

use App\Services\Parametros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppController extends Controller
{
    /**
     * Descarga del APK firmado de la app del repartidor (documento técnico 11).
     * Solo para repartidores y para el equipo de administración que la instala.
     */
    public function descargar(Request $request, Parametros $parametros): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->esAdministracion() || $usuario->esRepartidor(), 403);

        $disco = Storage::disk(config('pms.apk.disco'));
        $ruta = (string) config('pms.apk.ruta');

        abort_unless($disco->exists($ruta), 404, 'Aún no se ha publicado el instalador de la app.');

        return $disco->download($ruta, 'pms-track-'.$parametros->texto('app_version_actual').'.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
