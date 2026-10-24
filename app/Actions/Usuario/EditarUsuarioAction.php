<?php

namespace App\Actions\Usuario;

use App\Models\User;

class EditarUsuarioAction
{
    /**
     * @param  array{name:string,apellido:string,email:string,sede_id:?int,unidad_organica_id:?int}  $datos
     */
    public function ejecutar(User $trabajador, array $datos, bool $esJefeDeArea): User
    {
        $trabajador->update([
            'name' => $datos['name'],
            'apellido' => $datos['apellido'],
            'email' => $datos['email'],
            'sede_id' => $esJefeDeArea ? ($datos['sede_id'] ?? $trabajador->sede_id) : $trabajador->sede_id,
            'unidad_organica_id' => $esJefeDeArea ? $datos['unidad_organica_id'] : $trabajador->unidad_organica_id,
        ]);

        return $trabajador->fresh();
    }
}
