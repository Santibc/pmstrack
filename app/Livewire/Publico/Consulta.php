<?php

declare(strict_types=1);

namespace App\Livewire\Publico;

use App\Models\Guia;
use App\Services\Acceso\Captcha;
use App\Services\Guias\LineaDeTiempo;
use App\Services\Parametros;
use App\Support\Texto;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Consulta pública sin iniciar sesión (documento técnico 4.2 y 5.5): pide el
 * número de servicio y el código único, y muestra los datos de quien recibe
 * enmascarados. Protegida con límite de consultas por IP y captcha.
 */
#[Layout('layouts::publico')]
#[Title('Consulta de entregas')]
class Consulta extends Component
{
    public string $servicio = '';

    public string $codigoUnico = '';

    /** Campo trampa: las personas no lo ven; los robots suelen llenarlo. */
    public string $sitio = '';

    public string $captcha = '';

    #[Locked]
    public ?int $guiaId = null;

    public ?string $mensaje = null;

    public function consultar(Captcha $captcha, Parametros $parametros): void
    {
        $this->reset('guiaId', 'mensaje');

        $this->validate([
            'servicio' => ['required', 'string', 'max:30', 'regex:/^[0-9A-Za-z\-]+$/'],
            'codigoUnico' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
        ], [
            'servicio.required' => 'Escribe el número de servicio.',
            'servicio.regex' => 'El número de servicio solo tiene números.',
            'codigoUnico.required' => 'Escribe el código único del comercio.',
            'codigoUnico.regex' => 'El código único solo tiene números.',
        ]);

        $llave = 'consulta-publica|'.request()->ip();
        $limite = max(1, $parametros->entero('consulta_publica_limite'));

        if (RateLimiter::tooManyAttempts($llave, $limite)) {
            $this->mensaje = 'Hiciste varias consultas seguidas. Espera '.RateLimiter::availableIn($llave).' segundos e inténtalo de nuevo.';

            return;
        }

        RateLimiter::hit($llave, 60);

        if ($this->sitio !== '' || ! $captcha->verificar($this->captcha, request()->ip())) {
            $this->mensaje = 'No pudimos verificar la consulta. Recarga la página e inténtalo de nuevo.';
            $this->dispatch('captcha-reiniciar');

            return;
        }

        // Los ceros a la izquierda del código único no cuentan (Credibanco los envía sin ellos).
        $codigo = ltrim(Texto::digitos($this->codigoUnico), '0');

        $this->guiaId = Guia::query()
            ->where('servicio', trim($this->servicio))
            ->whereHas('comercio', fn ($q) => $q->where('codigo_unico', $codigo))
            ->value('id');

        if ($this->guiaId === null) {
            // El mismo mensaje sin importar cuál de los dos datos no coincide.
            $this->mensaje = 'No encontramos un servicio con esos datos. Revisa el número de servicio y el código único del comercio.';
        }

        $this->dispatch('captcha-reiniciar');
    }

    public function render(Captcha $captcha, Parametros $parametros, LineaDeTiempo $linea)
    {
        $guia = $this->guiaId === null ? null : Guia::query()
            ->with(['comercio', 'ciudad', 'seccional', 'zona', 'repartidor.user', 'eventos', 'ultimaVisita.causal', 'ultimaVisita.evidencias'])
            ->find($this->guiaId);

        return view('livewire.publico.consulta', [
            'guia' => $guia,
            'pasos' => $guia ? $linea->pasos($guia) : [],
            'llaveCaptcha' => $captcha->llavePublica(),
            'linea' => $parametros->texto('contacto_linea'),
            'direccionPms' => $parametros->texto('contacto_direccion'),
        ]);
    }
}
