<?php

namespace App\Http\Requests\Api;

use App\Enums\TipoNovedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NovedadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->repartidor !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'tipo' => ['required', Rule::enum(TipoNovedad::class)],
            'descripcion' => ['required', 'string', 'max:2000'],
            'guia_id' => ['nullable', 'integer', 'exists:guias,id'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'reportada_at' => ['nullable', 'date'],
        ];
    }
}
