<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrofeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:100'],
            'ambito' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'string', 'max:500'],
            'imagen' => ['nullable', 'string', 'max:500'],
        ];
    }
}