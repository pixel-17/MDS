<?php

namespace App\Http\Requests\Papeleta;

use App\Models\Sustento;
use Illuminate\Foundation\Http\FormRequest;

class SubirSustentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sustento = $this->route('sustento');

        if (! $sustento instanceof Sustento) {
            return false;
        }

        return $sustento->papeleta->trabajador_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
