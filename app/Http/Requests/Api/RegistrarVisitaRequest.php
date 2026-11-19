<?php

namespace App\Http\Requests\Api;

use App\Enums\ResultadoVisita;
use App\Models\Parada;
use App\Services\Visitas\DatosVisita;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Parada $parada */
        $parada = $this->route('parada');

        return $this->user()->can('registrarVisita', $parada);
    }

    /**
     * Las reglas de negocio (qué exige cada resultado) viven en VisitaService;
     * aquí solo se valida la forma de los datos.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $imagen = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('pms.evidencias.max_kb')];

        return [
            'uuid' => ['required', 'uuid'],
            'resultado' => ['required', Rule::enum(ResultadoVisita::class)],
            'registrada_at' => ['required', 'date'],
            'causal_id' => ['nullable', 'integer', 'exists:causales,id'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'rollos_entregados' => ['nullable', 'integer', 'min:1', 'max:65000'],
            'recibe_nombre' => ['nullable', 'string', 'max:120'],
            'recibe_cedula' => ['nullable', 'string', 'max:20', 'regex:/^[0-9.\- ]+$/'],
            'recibe_telefono' => ['nullable', 'string', 'max:20'],
            'autoriza_datos' => ['nullable', 'boolean'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'precision' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dispositivo' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'foto' => $imagen,
            'firma' => $imagen,
            'fachada' => $imagen,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recibe_cedula.regex' => 'La cédula solo puede tener números.',
            'foto.max' => 'La foto es demasiado pesada.',
        ];
    }

    public function datos(): DatosVisita
    {
        $registrada = CarbonImmutable::parse($this->validated('registrada_at'))->setTimezone(config('app.timezone'));

        // Un reloj del celular adelantado no puede dejar visitas en el futuro.
        if ($registrada->greaterThan(now()->addMinutes(5))) {
            $registrada = CarbonImmutable::now();
        }

        return new DatosVisita(
            uuid: $this->validated('uuid'),
            resultado: ResultadoVisita::from($this->validated('resultado')),
            registradaAt: $registrada,
            causalId: $this->filled('causal_id') ? (int) $this->validated('causal_id') : null,
            observacion: $this->filled('observacion') ? trim((string) $this->validated('observacion')) : null,
            rollosEntregados: $this->filled('rollos_entregados') ? (int) $this->validated('rollos_entregados') : null,
            recibeNombre: $this->filled('recibe_nombre') ? trim((string) $this->validated('recibe_nombre')) : null,
            recibeCedula: $this->filled('recibe_cedula') ? preg_replace('/\D/', '', (string) $this->validated('recibe_cedula')) : null,
            recibeTelefono: $this->filled('recibe_telefono') ? trim((string) $this->validated('recibe_telefono')) : null,
            autorizaDatos: $this->boolean('autoriza_datos'),
            latitud: $this->filled('latitud') ? (float) $this->validated('latitud') : null,
            longitud: $this->filled('longitud') ? (float) $this->validated('longitud') : null,
            precision: $this->filled('precision') ? (float) $this->validated('precision') : null,
            dispositivo: $this->validated('dispositivo'),
            appVersion: $this->validated('app_version'),
            foto: $this->file('foto'),
            firma: $this->file('firma'),
            fachada: $this->file('fachada'),
        );
    }
}
