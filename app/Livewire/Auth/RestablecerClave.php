<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglasPassword;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::publico')]
#[Title('Crear contraseña nueva')]
class RestablecerClave extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function guardar(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', ReglasPassword::defaults()],
        ], attributes: ['email' => 'correo', 'password' => 'contraseña']);

        $estado = Password::reset(
            $this->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $usuario): void {
                $usuario->forceFill([
                    'password' => $this->password,
                    'remember_token' => Str::random(60),
                    'password_cambiada_at' => now(),
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                ])->save();

                // La contraseña nueva cierra las sesiones de la app en otros celulares.
                $usuario->tokens()->delete();

                event(new PasswordReset($usuario));
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($estado)]);
        }

        session()->flash('status', 'Tu contraseña quedó actualizada. Ya puedes ingresar.');
        $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.restablecer-clave');
    }
}
