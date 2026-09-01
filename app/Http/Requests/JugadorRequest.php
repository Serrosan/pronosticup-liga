<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JugadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $camposNumericos = ['altura', 'id_externo_api'];

        foreach ($camposNumericos as $campo) {
            if ($this->has($campo) && $this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['nullable', 'string', 'max:255'],
            'nombre_camiseta' => ['nullable', 'string', 'max:255'],
            'posicion' => ['nullable', 'string', 'max:50'],
            'posicion_detallada' => ['nullable', 'string', 'max:100'],
            'pie' => ['nullable', 'string', 'max:50'],
            'nacionalidad' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'lugar_nacimiento' => ['nullable', 'string', 'max:255'],
            'seleccion' => ['nullable', 'string', 'max:255'],
            'altura' => ['nullable', 'integer'],
            'fecha_fin_contrato' => ['nullable', 'date'],
            'club_anterior' => ['nullable', 'string', 'max:255'],
            'id_externo_api' => ['nullable', 'integer'],
            'foto_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}