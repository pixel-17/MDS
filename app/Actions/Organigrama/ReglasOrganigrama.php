<?php

namespace App\Actions\Organigrama;

use App\Models\UnidadOrganica;
use App\Models\User;

class ReglasOrganigrama
{

    public function areaDe(User $jefe, UnidadOrganica $unidad): ?int
    {
        $area = null;
        $vistas = [];

        for ($u = $unidad; $u && ! isset($vistas[$u->id]); $u = $u->parent_id ? UnidadOrganica::find($u->parent_id) : null) {
            $vistas[$u->id] = true;

            if ((int) $u->jefe_id === (int) $jefe->id && $u->hijos()->exists()) {
                $area = (int) $u->id;
            }
        }

        return $area;
    }

    public function puedeGestionarJefaturasEn(User $actor, UnidadOrganica $unidad): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        $area = $this->areaDe($actor, $unidad);

        return $area !== null && (int) $area !== (int) $unidad->id;
    }

    public function esJefeDeAreaDe(User $user, int $unidadId): bool
    {
        $unidad = UnidadOrganica::find($unidadId);

        return $unidad !== null && $this->areaDe($user, $unidad) !== null;
    }
}
