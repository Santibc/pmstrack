<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\Notifica;
use App\Models\Acceso;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mi cuenta')]
class Cuenta extends Component
{
    use Notifica;

    public string $password_actual = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function cambiarPassword(): void
    {
        $this->validate([
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:password_actual', Password::defaults()],
        ], attributes: ['password_actual' => 'contraseña actual', 'password' => 'contraseña nueva']);

        $usuario = Auth::user();

        if (! Hash::check($this->password_actual, $usuario->password)) {
            throw ValidationException::withMessages(['password_actual' => 'La contraseña actual no es correcta.']);
        }

        $usuario->forceFill(['password' => $this->password, 'password_cambiada_at' => now()])->save();
        Auth::logoutOtherDevices($this->password);

        $this->reset('password_actual', 'password', 'password_confirmation');
        $this->avisar('Tu contraseña quedó actualizada.');
    }

    public function render()
    {
        return view('livewire.cuenta', [
            'usuario' => Auth::user()->load('seccional'),
            'accesos' => Acceso::query()->where('user_id', Auth::id())->latest('created_at')->limit(8)->get(),
        ]);
    }
}
