<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::publico')]
#[Title('Recuperar contraseña')]
class OlvideClave extends Component
{
    #[Validate('required|string|email|max:150', as: 'correo')]
    public string $email = '';

    public bool $enviado = false;

    public function enviar(): void
    {
        $this->validate();

        Password::sendResetLink(['email' => $this->email]);

        // La respuesta es la misma exista o no el correo, para no revelar qué usuarios hay.
        $this->enviado = true;
    }

    public function render()
    {
        return view('livewire.auth.olvide-clave');
    }
}
