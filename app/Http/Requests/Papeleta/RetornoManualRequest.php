<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;


class RetornoManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('marcarRetornoManual', $this->route('papeleta'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'justificacion' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
