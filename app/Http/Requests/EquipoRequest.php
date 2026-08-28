<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'nombre_corto' => ['nullable', 'string', 'max:255'],
            'apodo' => ['nullable', 'string', 'max:255'],
            'siglas' => ['nullable', 'string', 'max:10'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'id_estadio' => ['nullable', 'integer', 'exists:estadios,id'],
            'año_fundacion' => ['nullable', 'integer'],
            'escudo_url' => ['nullable', 'string', 'max:255'],
            'color_primario' => ['nullable', 'string', 'max:50'],
            'color_secundario' => ['nullable', 'string', 'max:50'],
            'num_socios' => ['nullable', 'integer'],
            'num_abonados' => ['nullable', 'integer'],
            'camiseta_1' => ['nullable', 'string', 'max:500'],
            'camiseta_2' => ['nullable', 'string', 'max:500'],
            'camiseta_3' => ['nullable', 'string', 'max:500'],
            'camiseta_1_reverso' => ['nullable', 'string', 'max:500'],
            'camiseta_2_reverso' => ['nullable', 'string', 'max:500'],
            'camiseta_3_reverso' => ['nullable', 'string', 'max:500'],
            'id_externo_api' => ['nullable', 'integer'],
            'id_equipo_api' => ['nullable', 'integer'],
        ];
    }
}