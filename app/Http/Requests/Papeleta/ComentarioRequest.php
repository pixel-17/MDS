<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

class ComentarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comentario' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
