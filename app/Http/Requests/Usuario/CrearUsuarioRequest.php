<?php

namespace App\Http\Requests\Usuario;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Support\ReglasDatosUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CrearUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadesDisponibles = $this->unidadesDisponibles();
        $esJefeDeArea = $unidadesDisponibles->isNotEmpty();

        $existenteInactivo = User::where('dni', $this->input('dni'))
            ->where('activo', false)
            ->first();

        return [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => ReglasDatosUsuario::dni(soloActivos: true),
            'email' => ReglasDatosUsuario::email($existenteInactivo?->id),
            'regimen' => ['required', Rule::in(['276', '728'])],

            'turno' => ['nullable', Rule::in(['MANANA', 'TARDE', 'NOCHE'])],
            'fecha_ancla' => ['nullable', 'required_with:turno', 'date'],
            'dias_trabajo' => ['nullable', 'integer', 'min:1', 'max:30'],
            'dias_descanso' => ['nullable', 'integer', 'min:1', 'max:30'],

            'sede_id' => $esJefeDeArea && $this->input('tipo') === 'jefe_inmediato'
                ? ['required', 'exists:sedes,id']
                : ['nullable', 'exists:sedes,id'],
            'tipo' => $esJefeDeArea
                ? ['required', Rule::in(['trabajador', 'jefe_inmediato'])]
                : ['nullable'],
            'unidad_organica_id' => $esJefeDeArea
                ? ['required', Rule::in($unidadesDisponibles->all())]
                : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.size' => 'El DNI debe tener 8 dígitos.',
            'dni.unique' => 'Ya existe un usuario activo con ese DNI.',
            'email.unique' => 'Ese correo ya está en uso por otro usuario.',
            'unidad_organica_id.in' => 'Esa unidad no pertenece a tu área.',
            'sede_id.required' => 'Un jefe inmediato debe tener sede: es necesaria para los cierres.',
            'fecha_ancla.required_with' => 'Indica desde cuándo empieza su próximo bloque de trabajo.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $regimenEsperado = $this->regimenEsperado();
            $regimenEnviado = $this->input('regimen');

            if ($regimenEsperado !== null && $regimenEnviado !== null && $regimenEnviado !== $regimenEsperado) {
                $validator->errors()->add(
                    'regimen',
                    "El régimen debe coincidir con el de su jefe inmediato (régimen {$regimenEsperado})."
                );
            }
        });
    }

    private function regimenEsperado(): ?string
    {
        $unidadesDisponibles = $this->unidadesDisponibles();
        $esJefeDeArea = $unidadesDisponibles->isNotEmpty();

        if (! $esJefeDeArea) {
            return $this->user()->regimen;
        }

        if ($this->input('tipo') !== 'trabajador') {
            return null;
        }

        $unidad = UnidadOrganica::find($this->input('unidad_organica_id'));

        return $unidad?->jefe?->regimen;
    }

    public function unidadesDisponibles(): Collection
    {
        $encabezadas = $this->user()->unidadesDeArea;

        $ids = collect();
        foreach ($encabezadas as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
