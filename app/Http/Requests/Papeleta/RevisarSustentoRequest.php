<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

class RevisarSustentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resultado' => ['required', 'in:aprobado,observado,particular'],
            'comentario' => ['required_if:resultado,observado,particular', 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
