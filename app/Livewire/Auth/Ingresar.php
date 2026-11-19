<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Services\Acceso\AutenticacionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::publico')]
#[Title('Ingresar')]
class Ingresar extends Component
{
    #[Validate('required|string|email|max:150', as: 'correo')]
    public string $email = '';

    #[Validate('required|string|max:200', as: 'contraseña')]
    public string $password = '';

    public bool $recordar = false;

    public function ingresar(AutenticacionService $autenticacion): void
    {
        $this->validate();

        $llave = 'ingreso|'.request()->ip();

        if (RateLimiter::tooManyAttempts($llave, 10)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($llave).' segundos e inténtalo de nuevo.',
            ]);
        }

        RateLimiter::hit($llave, 60);

        $usuario = $autenticacion->validar($this->email, $this->password, AutenticacionService::WEB, request());

        RateLimiter::clear($llave);
        Auth::login($usuario, $this->recordar);
        session()->regenerate();

        $this->redirectIntended(default: $usuario->rutaInicio());
    }

    public function render()
    {
        return view('livewire.auth.ingresar');
    }
}
