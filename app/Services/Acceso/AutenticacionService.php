<?php

declare(strict_types=1);

namespace App\Services\Acceso;

use App\Models\Acceso;
use App\Models\Repartidor;
use App\Models\User;
use App\Services\Parametros;
use App\Support\Texto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso con usuario y contraseña para la web y la app: bloqueo tras
 * intentos fallidos y registro de cada acceso (documento técnico 4.1 y 9).
 */
final class AutenticacionService
{
    public const string WEB = 'web';

    public const string APP = 'app';

    public function __construct(private readonly Parametros $parametros) {}

    /**
     * @param  string  $identificador  Correo o, para repartidores, la cédula.
     *
     * @throws ValidationException
     */
    public function validar(string $identificador, string $password, string $canal, Request $request, string $campo = 'email'): User
    {
        $identificador = trim($identificador);
        $usuario = $this->buscar($identificador);

        if ($usuario === null) {
            // Se gasta el mismo tiempo que con un usuario real para no revelar cuáles existen.
            Hash::check($password, '$2y$12$MfB9/oC.pBOrNbUEJ.JV..qSCdaBaPQUtUSD59NlrzBRrIqJiElsq');
            $this->registrar(null, $identificador, $canal, false, 'usuario_inexistente', $request);
            $this->fallar($campo, 'El usuario o la contraseña no son correctos.');
        }

        if ($usuario->estaBloqueado()) {
            $minutos = max(1, (int) ceil(now()->diffInSeconds($usuario->bloqueado_hasta) / 60));
            $this->registrar($usuario, $identificador, $canal, false, 'bloqueado', $request);
            $this->fallar($campo, "Usuario bloqueado por intentos fallidos. Intenta de nuevo en {$minutos} ".($minutos === 1 ? 'minuto' : 'minutos').'.');
        }

        if (! Hash::check($password, $usuario->password)) {
            $this->contarFallo($usuario);
            $this->registrar($usuario, $identificador, $canal, false, 'clave_incorrecta', $request);
            $this->fallar($campo, 'El usuario o la contraseña no son correctos.');
        }

        if (! $usuario->activo) {
            $this->registrar($usuario, $identificador, $canal, false, 'inactivo', $request);
            $this->fallar($campo, 'Tu usuario está desactivado. Comunícate con la administración de PMS.');
        }

        if ($mensaje = $this->canalNoPermitido($usuario, $canal)) {
            $this->registrar($usuario, $identificador, $canal, false, 'canal_no_permitido', $request);
            $this->fallar($campo, $mensaje);
        }

        $usuario->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null, 'ultimo_acceso_at' => now()])->saveQuietly();
        $this->registrar($usuario, $identificador, $canal, true, null, $request);

        return $usuario;
    }

    private function buscar(string $identificador): ?User
    {
        if ($identificador === '') {
            return null;
        }

        if (str_contains($identificador, '@')) {
            return User::query()->where('email', Str::lower($identificador))->first();
        }

        $cedula = Texto::digitos($identificador);

        return $cedula === '' ? null : Repartidor::query()->where('cedula', $cedula)->first()?->user;
    }

    private function canalNoPermitido(User $usuario, string $canal): ?string
    {
        if ($canal === self::APP) {
            return $usuario->esRepartidor() && $usuario->repartidor?->activo
                ? null
                : 'La app es solo para repartidores activos. Ingresa por la plataforma web.';
        }

        return $usuario->esAdministracion() || $usuario->esCredibanco()
            ? null
            : 'Los repartidores ingresan desde la app del celular.';
    }

    private function contarFallo(User $usuario): void
    {
        $intentos = $usuario->intentos_fallidos + 1;
        $limite = max(1, $this->parametros->entero('bloqueo_intentos'));

        $usuario->forceFill($intentos >= $limite
            ? ['intentos_fallidos' => 0, 'bloqueado_hasta' => now()->addMinutes(max(1, $this->parametros->entero('bloqueo_minutos')))]
            : ['intentos_fallidos' => $intentos],
        )->saveQuietly();
    }

    private function registrar(?User $usuario, string $identificador, string $canal, bool $exitoso, ?string $motivo, Request $request): void
    {
        Acceso::query()->create([
            'user_id' => $usuario?->id,
            'identificador' => Str::limit($identificador, 150, ''),
            'canal' => $canal,
            'exitoso' => $exitoso,
            'motivo' => $motivo,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'created_at' => now(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function fallar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
