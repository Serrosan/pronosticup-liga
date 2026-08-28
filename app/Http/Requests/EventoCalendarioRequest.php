<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventoCalendarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('color')) {
            $this->merge(['color' => '#FFB238']);
        }
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'titulo' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}