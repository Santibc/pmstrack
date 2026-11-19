<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NovedadRequest;
use App\Models\Causal;
use App\Models\Dispositivo;
use App\Models\Novedad;
use App\Services\Rutas\RutaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Catálogos, novedades y registro del dispositivo para la app.
 */
class OperacionController extends Controller
{
    /**
     * Catálogo de causales de no entrega y anulación.
     */
    public function causales(): JsonResponse
    {
        return response()->json([
            'data' => Causal::query()->activas()->get()->map(fn (Causal $causal): array => [
                'id' => $causal->id,
                'tipo' => $causal->tipo->value,
                'nombre' => $causal->nombre,
            ]),
        ]);
    }

    /**
     * Reporta una novedad a la administradora (vía cerrada, accidente, dirección no encontrada).
     */
    public function novedad(NovedadRequest $request, RutaService $rutas): JsonResponse
    {
        $repartidor = $request->user()->repartidor;
        $datos = $request->validated();

        $reportada = isset($datos['reportada_at'])
            ? CarbonImmutable::parse($datos['reportada_at'])->setTimezone(config('app.timezone'))
            : CarbonImmutable::now();

        $novedad = Novedad::query()->firstOrCreate(
            ['uuid' => $datos['uuid']],
            [
                'repartidor_id' => $repartidor->id,
                'ruta_id' => $rutas->delDia($repartidor)?->id,
                'guia_id' => $datos['guia_id'] ?? null,
                'tipo' => $datos['tipo'],
                'descripcion' => trim($datos['descripcion']),
                'latitud' => $datos['latitud'] ?? null,
                'longitud' => $datos['longitud'] ?? null,
                'reportada_at' => $reportada->greaterThan(now()->addMinutes(5)) ? now() : $reportada,
                'estado' => Novedad::ABIERTA,
            ],
        );

        return response()->json(['data' => [
            'id' => $novedad->id,
            'uuid' => $novedad->uuid,
            'estado' => $novedad->estado,
        ]], $novedad->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Registra el token de notificaciones del celular.
     */
    public function dispositivo(Request $request): Response
    {
        $datos = $request->validate([
            'push_token' => ['required', 'string', 'max:255'],
            'plataforma' => ['nullable', 'string', 'in:android,ios'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'so_version' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        $usuario = $request->user();

        // Un celular avisa a un solo usuario: si cambió de manos, el token deja al anterior.
        Dispositivo::query()
            ->where('push_token', $datos['push_token'])
            ->where('user_id', '!=', $usuario->id)
            ->update(['push_token' => null]);

        $usuario->dispositivos()->updateOrCreate(
            ['push_token' => $datos['push_token']],
            [
                'plataforma' => $datos['plataforma'] ?? 'android',
                'modelo' => $datos['modelo'] ?? null,
                'so_version' => $datos['so_version'] ?? null,
                'app_version' => $datos['app_version'] ?? null,
                'ultimo_uso_at' => now(),
            ],
        );

        // Limpia el registro sin token que se creó al iniciar sesión.
        $usuario->dispositivos()->whereNull('push_token')->delete();

        return response()->noContent();
    }
}
