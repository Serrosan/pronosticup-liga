<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CodigoCanjeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // El formulario de edición rápida envía '' cuando un campo se deja en blanco
        // (el select de carta, o el número de usos máximos) — lo normalizamos a null
        // antes de validar, para que "nullable" funcione tal cual se espera.
        $this->merge([
            'id_tipo_carta' => $this->id_tipo_carta === '' ? null : $this->id_tipo_carta,
            'usos_maximos' => $this->usos_maximos === '' ? null : $this->usos_maximos,
        ]);
    }

    public function rules(): array
    {
        $idIgnorar = $this->route('codigoCanje')?->id;

        return [
            'codigo' => ['required', 'string', 'max:100', Rule::unique('codigos_canje', 'codigo')->ignore($idIgnorar)],
            'nombre' => ['required', 'string', 'max:150'],
            'tipo_premio' => ['required', Rule::in(['tirada_aleatoria', 'carta_especifica'])],
            'id_tipo_carta' => ['required_if:tipo_premio,carta_especifica', 'nullable', 'exists:tipos_carta,id'],
            'usos_maximos' => ['nullable', 'integer', 'min:1'],
            'activo' => ['boolean'],
        ];
    }
}