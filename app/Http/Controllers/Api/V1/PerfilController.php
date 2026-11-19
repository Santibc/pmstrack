<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use App\Services\Parametros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PerfilController extends Controller
{
    /**
     * Perfil e indicadores del mes del repartidor.
     */
    public function show(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user()->load(['repartidor.seccional', 'repartidor.zonaHabitual']));
    }

    public function cambiarPassword(Request $request): Response
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:password_actual', Password::defaults()],
        ], attributes: ['password_actual' => 'contraseña actual', 'password' => 'contraseña nueva']);

        $usuario = $request->user();

        if (! Hash::check($datos['password_actual'], $usuario->password)) {
            throw ValidationException::withMessages(['password_actual' => 'La contraseña actual no es correcta.']);
        }

        $usuario->forceFill(['password' => $datos['password'], 'password_cambiada_at' => now()])->save();

        // Los demás celulares deben volver a iniciar sesión.
        $usuario->tokens()->whereKeyNot($usuario->currentAccessToken()->getKey())->delete();

        return response()->noContent();
    }

    /**
     * Versión vigente de la app y enlace privado de descarga del APK.
     */
    public function version(Parametros $parametros): JsonResponse
    {
        return response()->json([
            'version_actual' => $parametros->texto('app_version_actual'),
            'version_minima' => $parametros->texto('app_version_minima'),
            'url_descarga' => route('app.descargar'),
        ]);
    }
}
