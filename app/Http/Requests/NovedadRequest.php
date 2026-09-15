<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NovedadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_liga' => ['nullable', 'exists:ligas,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'activa' => ['boolean'],
        ];
    }
}