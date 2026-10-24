<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

class RetornoManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marcarRetornoManual', $this->route('papeleta'));
    }

    public function rules(): array
    {
        return [
            'justificacion' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
