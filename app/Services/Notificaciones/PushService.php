<?php

declare(strict_types=1);

namespace App\Services\Notificaciones;

use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos a la app del repartidor (nueva planeación, cambios y guías urgentes)
 * por el servicio de notificaciones de Expo, que entrega por FCM en Android.
 *
 * Nunca interrumpe la operación: si el envío falla, se registra y se continúa.
 */
final class PushService
{
    /**
     * @param  User|iterable<User>  $destinatarios
     * @param  array<string, mixed>  $datos
     */
    public function enviar(User|iterable $destinatarios, string $titulo, string $cuerpo, array $datos = []): void
    {
        if (! config('pms.push.activo')) {
            return;
        }

        $ids = collect($destinatarios instanceof User ? [$destinatarios] : $destinatarios)->pluck('id');

        $tokens = Dispositivo::query()
            ->whereIn('user_id', $ids)
            ->whereNotNull('push_token')
            ->pluck('push_token')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        // El envío sale después de responder al usuario, para no hacerlo esperar.
        dispatch(fn () => $this->despachar($tokens, $titulo, $cuerpo, $datos))->afterResponse();
    }

    /**
     * @param  Collection<int, string>  $tokens
     * @param  array<string, mixed>  $datos
     */
    private function despachar(Collection $tokens, string $titulo, string $cuerpo, array $datos): void
    {
        foreach ($tokens->chunk(100) as $lote) {
            try {
                $respuesta = Http::timeout(8)
                    ->acceptJson()
                    ->when(config('pms.push.token_acceso'), fn ($http, string $token) => $http->withToken($token))
                    ->post((string) config('pms.push.url'), $lote->map(fn (string $token): array => [
                        'to' => $token,
                        'title' => $titulo,
                        'body' => $cuerpo,
                        'data' => $datos === [] ? null : $datos,
                        'sound' => 'default',
                        'priority' => 'high',
                        'channelId' => 'operacion',
                    ])->values()->all());

                $this->limpiarTokensInvalidos($lote->values(), $respuesta->json('data', []));
            } catch (Throwable $error) {
                Log::warning('No se pudo enviar la notificación a la app', ['error' => $error->getMessage()]);
            }
        }
    }

    /**
     * Expo informa cuando un dispositivo ya no está registrado: se olvida su token.
     *
     * @param  Collection<int, string>  $tokens
     * @param  array<int, array<string, mixed>>  $resultados
     */
    private function limpiarTokensInvalidos(Collection $tokens, array $resultados): void
    {
        $invalidos = [];

        foreach ($resultados as $indice => $resultado) {
            if (($resultado['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($tokens[$indice])) {
                $invalidos[] = $tokens[$indice];
            }
        }

        if ($invalidos !== []) {
            Dispositivo::query()->whereIn('push_token', $invalidos)->update(['push_token' => null]);
        }
    }
}
