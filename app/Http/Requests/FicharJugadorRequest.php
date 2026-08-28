<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FicharJugadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_equipo_nuevo' => ['required', 'integer', 'exists:equipos,id'],
            'fecha_fichaje' => ['required', 'date'],
            'dorsal' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}