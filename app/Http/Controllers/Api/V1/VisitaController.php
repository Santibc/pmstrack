<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegistrarVisitaRequest;
use App\Models\Parada;
use App\Services\Visitas\VisitaService;
use Illuminate\Http\JsonResponse;

class VisitaController extends Controller
{
    /**
     * Registra el resultado de una parada con su evidencia (multipart).
     * El uuid lo genera la app: reenviar la misma visita no la duplica.
     */
    public function store(RegistrarVisitaRequest $request, Parada $parada, VisitaService $visitas): JsonResponse
    {
        $visita = $visitas->registrar($parada, $request->user()->repartidor, $request->datos());

        return response()->json(['data' => [
            'uuid' => $visita->uuid,
            'parada_id' => $visita->parada_id,
            'resultado' => $visita->resultado->value,
            'rollos_entregados' => $visita->rollos_entregados,
            'numero_intento' => $visita->numero_intento,
            'registrada_at' => $visita->registrada_at->toIso8601String(),
            'sincronizada_at' => $visita->sincronizada_at->toIso8601String(),
            'evidencias' => $visita->evidencias->map(fn ($evidencia): array => [
                'tipo' => $evidencia->tipo->value,
                'sha256' => $evidencia->sha256,
            ]),
        ]], $visita->wasRecentlyCreated ? 201 : 200);
    }
}
