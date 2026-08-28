<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArbitroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['nullable', 'string', 'max:255'],
            'nacionalidad' => ['nullable', 'string', 'max:255'],
            'comunidad_autonoma' => ['nullable', 'string', 'max:255'],
            'anio_debut' => ['nullable', 'integer'],
            'promedio_tarjetas_amarillas' => ['nullable', 'numeric'],
            'promedio_tarjetas_rojas' => ['nullable', 'numeric'],
            'imagen' => ['nullable', 'string', 'max:500'],
        ];
    }
}