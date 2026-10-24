<?php

namespace App\Http\Requests\Papeleta;

class ObservarJefeRequest extends ComentarioRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'requiere_adjunto' => ['nullable', 'boolean'],
        ];
    }
}
