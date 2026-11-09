<?php

namespace App\Http\Requests\Papeleta;


class ObservarJefeRequest extends ComentarioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'requiere_adjunto' => ['nullable', 'boolean'],
        ];
    }
}
