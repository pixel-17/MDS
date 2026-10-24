<?php

namespace App\Http\Requests\Usuario;

use App\Models\User;
use App\Support\ReglasDatosUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class EditarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadesDisponibles = $this->unidadesDisponibles();
        $esJefeDeArea = $unidadesDisponibles->isNotEmpty();

        $trabajador = $this->route('trabajador');

        return [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ReglasDatosUsuario::email($trabajador->id),

            'sede_id' => $esJefeDeArea ? ['required', 'exists:sedes,id'] : ['nullable'],
            'unidad_organica_id' => $esJefeDeArea
                ? ['required', Rule::in($unidadesDisponibles->all())]
                : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Ese correo ya está en uso por otro usuario.',
            'unidad_organica_id.in' => 'Esa unidad no pertenece a tu área.',
        ];
    }

    public function unidadesDisponibles(): Collection
    {
        $encabezadas = $this->user()->unidadesQueEncabeza;

        $ids = collect();
        foreach ($encabezadas as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
