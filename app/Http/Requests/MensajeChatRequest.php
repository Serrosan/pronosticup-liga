<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MensajeChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'in:texto,imagen,audio'],
            'texto' => ['required_if:tipo,texto', 'nullable', 'string', 'max:500'],
            'adjunto_url' => ['required_if:tipo,imagen,audio', 'nullable', 'string', 'max:500'],
        ];
    }
}