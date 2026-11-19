<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:200'],
            'dispositivo' => ['nullable', 'array'],
            'dispositivo.nombre' => ['nullable', 'string', 'max:120'],
            'dispositivo.modelo' => ['nullable', 'string', 'max:120'],
            'dispositivo.plataforma' => ['nullable', 'string', 'in:android,ios'],
            'dispositivo.so_version' => ['nullable', 'string', 'max:40'],
            'dispositivo.app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['usuario' => 'usuario', 'password' => 'contraseña'];
    }
}
