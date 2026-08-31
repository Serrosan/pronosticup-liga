<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntrenadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'nacionalidad' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'id_equipo_actual' => ['nullable', 'integer', 'exists:equipos,id'],
        ];
    }
}