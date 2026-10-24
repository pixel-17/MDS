<?php

namespace App\Http\Requests\Papeleta;

use App\Models\Motivo;
use App\Models\Papeleta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePapeletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crear', Papeleta::class);
    }

    public function rules(): array
    {

        // 728 (rotativo, 24/7): incluye turno NOCHE (22:00-06:00, cruza

        $tope = $this->user()->regimen === '728'
            ? now()->addDay()->endOfDay()
            : now()->endOfDay();

        return [
            'motivo_id' => ['required', 'integer', Rule::exists('motivos', 'id')->where('activo', true)],
            'justificacion' => ['nullable', 'string', 'max:2000'],
            'adjunto_inicial_path' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],

            'hora_retorno_estimado' => ['nullable', 'date', 'after:now', 'before:'.$tope->toDateTimeString()],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $motivo = Motivo::find($this->input('motivo_id'));

            if ($motivo && $motivo->adjunto === 'obligatorio' && ! $this->filled('justificacion')) {
                $validator->errors()->add('justificacion', "La justificación es obligatoria para el motivo {$motivo->nombre}.");
            }
        });
    }
}
