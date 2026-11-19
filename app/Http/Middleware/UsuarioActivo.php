<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario que fue desactivado mientras estaba conectado.
 */
final class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && ! $usuario->activo) {
            if ($request->is('api/*')) {
                $usuario->currentAccessToken()?->delete();

                return response()->json(['message' => 'Tu usuario está desactivado. Comunícate con la administración de PMS.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Tu usuario está desactivado. Comunícate con la administración de PMS.');
        }

        return $next($request);
    }
}
