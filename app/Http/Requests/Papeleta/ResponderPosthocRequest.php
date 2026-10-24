<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

class ResponderPosthocRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'respuesta' => ['required', 'string', 'min:5', 'max:2000'],
            'archivo' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
