<?php

use App\Exceptions\ArchivoInvalido;
use App\Exceptions\OperacionNoPermitida;
use App\Http\Middleware\UsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El hosting termina el HTTPS en un proxy delante de PHP.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'usuario.activo' => UsuarioActivo::class,
        ]);

        $middleware->api(append: [\App\Http\Middleware\TamanoDeRespuesta::class]);

        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()->rutaInicio());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Reglas de negocio: en la API se devuelven como 422 con el mensaje para el repartidor.
        $exceptions->render(function (OperacionNoPermitida|ArchivoInvalido $error, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $error->getMessage()], 422);
            }

            return back()->with('error', $error->getMessage());
        });

        $exceptions->dontReport([OperacionNoPermitida::class, ArchivoInvalido::class]);
    })->create();
