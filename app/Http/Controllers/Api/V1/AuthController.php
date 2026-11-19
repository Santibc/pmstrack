<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Services\Acceso\AutenticacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Inicio de sesión de la app. Devuelve el token del dispositivo (revocable).
     */
    public function login(LoginRequest $request, AutenticacionService $autenticacion): JsonResponse
    {
        $usuario = $autenticacion->validar(
            $request->validated('usuario'),
            $request->validated('password'),
            AutenticacionService::APP,
            $request,
            'usuario',
        );

        $dispositivo = $request->validated('dispositivo') ?? [];
        $nombre = Str::limit($dispositivo['nombre'] ?? $dispositivo['modelo'] ?? 'Celular', 120, '');

        // Un token por celular: al volver a entrar desde el mismo equipo se reemplaza el anterior.
        $usuario->tokens()->where('name', $nombre)->delete();
        $token = $usuario->createToken($nombre, ['app'])->plainTextToken;

        if ($dispositivo !== []) {
            $usuario->dispositivos()->updateOrCreate(
                ['modelo' => $dispositivo['modelo'] ?? $nombre, 'push_token' => null],
                [
                    'plataforma' => $dispositivo['plataforma'] ?? 'android',
                    'so_version' => $dispositivo['so_version'] ?? null,
                    'app_version' => $dispositivo['app_version'] ?? null,
                    'ultimo_uso_at' => now(),
                ],
            );
        }

        $usuario->load(['repartidor.seccional', 'repartidor.zonaHabitual']);

        return response()->json([
            'token' => $token,
            'usuario' => new UsuarioResource($usuario),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
