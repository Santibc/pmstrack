<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Declara el tamaño exacto de las respuestas JSON de la API.
 *
 * Sin `Content-Length`, el celular lee hasta que el servidor cierra la
 * conexión; con el servidor de desarrollo de PHP en Windows el último byte se
 * pierde a veces y el JSON llega incompleto. Con el tamaño declarado el
 * cliente sabe exactamente cuánto leer.
 */
final class TamanoDeRespuesta
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if (! $respuesta instanceof StreamedResponse && ! $respuesta instanceof BinaryFileResponse) {
            $contenido = $respuesta->getContent();

            if (is_string($contenido) && ! $respuesta->headers->has('Content-Length')) {
                $respuesta->headers->set('Content-Length', (string) strlen($contenido));
            }
        }

        return $respuesta;
    }
}
