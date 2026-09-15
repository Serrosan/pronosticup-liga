<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TipoCartaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_categoria' => ['required', 'exists:categorias_carta,id'],
            'rareza' => ['required', 'in:Comun,PocoComun,Rara,Legendaria'],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['required', 'string'],
            'codigo_efecto' => ['required', 'string', 'max:100'],
            'activa' => ['boolean'],
        ];
    }
}