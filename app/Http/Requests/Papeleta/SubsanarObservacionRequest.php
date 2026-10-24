<?php

namespace App\Http\Requests\Papeleta;

use App\Models\Papeleta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubsanarObservacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $papeleta = $this->route('papeleta');

        if (! $papeleta instanceof Papeleta) {
            return false;
        }

        return (int) $papeleta->trabajador_id === (int) $this->user()->id;
    }

    public function rules(): array
    {
        $papeleta = $this->route('papeleta');
        $exigeAdjunto = $papeleta instanceof Papeleta && (bool) $papeleta->observacion_requiere_adjunto;

        return [
            'respuesta' => ['required', 'string', 'min:5', 'max:2000'],
            'archivo' => [
                Rule::requiredIf($exigeAdjunto),
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png',
            ],
        ];
    }
}
