<?php

declare(strict_types=1);

namespace App\Services\Acceso;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Captcha de la consulta pública (Cloudflare Turnstile). Solo se activa cuando
 * hay llaves configuradas; el límite de consultas por IP aplica siempre.
 */
final class Captcha
{
    private const string URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function activo(): bool
    {
        return filled(config('pms.turnstile.site_key')) && filled(config('pms.turnstile.secret_key'));
    }

    public function llavePublica(): ?string
    {
        return $this->activo() ? (string) config('pms.turnstile.site_key') : null;
    }

    public function verificar(?string $token, ?string $ip): bool
    {
        if (! $this->activo()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            return (bool) Http::asForm()->timeout(6)->post(self::URL, [
                'secret' => config('pms.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ])->json('success', false);
        } catch (Throwable) {
            // Si Cloudflare no responde no se bloquea la consulta: sigue protegida por el límite por IP.
            return true;
        }
    }
}
