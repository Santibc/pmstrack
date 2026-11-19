<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GuardarOrdenRequest extends FormRequest
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
            'paradas' => ['required', 'array', 'min:1'],
            'paradas.*' => ['integer', 'distinct'],
        ];
    }
}
