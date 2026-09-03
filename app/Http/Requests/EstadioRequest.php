<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstadioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'capacidad' => ['nullable', 'integer'],
            'tamanio_campo' => ['nullable', 'string', 'max:50'],
            'anio_construccion' => ['nullable', 'integer'],
            'anio_ult_remodelacion' => ['nullable', 'integer'],
            'foto_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}