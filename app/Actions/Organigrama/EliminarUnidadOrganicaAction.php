<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\QueryException;

class EliminarUnidadOrganicaAction
{
    /** @throws UsuarioException */
    public function ejecutar(User $actor, UnidadOrganica $unidad): void
    {
        if (! $actor->hasRole('admin')) {
            throw new UsuarioException('Solo un administrador puede eliminar unidades orgánicas.');
        }

        if ($unidad->hijos()->exists()) {
            throw new UsuarioException('La unidad tiene sub-unidades. Muévelas o elimínalas primero.');
        }

        if ($unidad->miembros()->exists()) {
            throw new UsuarioException('La unidad tiene personas asignadas. Muévelas a otra unidad o desactiva la unidad.');
        }

        try {
            $unidad->delete();
        } catch (QueryException $e) {

            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            throw new UsuarioException('La unidad tiene sub-unidades, personas o historial asociado y no puede borrarse. Muévelas o desactiva la unidad.');
        }
    }
}
