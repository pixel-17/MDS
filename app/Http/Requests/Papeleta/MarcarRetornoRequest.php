<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

class MarcarRetornoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marcarRetorno', $this->route('papeleta'));
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'max:5120'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
