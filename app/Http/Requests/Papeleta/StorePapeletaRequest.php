<?php

namespace App\Http\Requests\Papeleta;

use App\Models\Motivo;
use App\Models\Papeleta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;


class StorePapeletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crear', Papeleta::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        
        
        
        
        
        
        $tope = $this->user()?->regimen === '728'
            ? now()->addDay()->endOfDay()
            : now()->endOfDay();

        return [
            'motivo_id' => ['required', 'integer', Rule::exists('motivos', 'id')->where('activo', true)],
            'justificacion' => ['nullable', 'string', 'max:2000'],
            'adjunto_inicial_path' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            
            
            'hora_retorno_estimado' => ['nullable', 'date', 'after:now', 'before:'.$tope->toDateTimeString()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $motivo = Motivo::find((int) $this->input('motivo_id'));

            if ($motivo && $motivo->adjunto === 'obligatorio' && ! $this->filled('justificacion')) {
                $validator->errors()->add('justificacion', "La justificación es obligatoria para el motivo {$motivo->nombre}.");
            }
        });
    }
}
