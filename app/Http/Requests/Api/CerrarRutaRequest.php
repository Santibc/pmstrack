<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CerrarRutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operar', $this->route('ruta'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rollos_devueltos' => ['required', 'integer', 'min:0', 'max:100000'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'cerrada_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['rollos_devueltos' => 'rollos que devuelves'];
    }
}
