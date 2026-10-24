<?php

namespace App\Observers;

use App\Actions\Organigrama\PapeletasAbiertasService;
use App\Events\HorarioActualizado;
use App\Models\Turno;
use App\Models\UnidadOrganica;
use App\Models\User;

class UserObserver
{
    public function saving(User $user): void
    {
        if (! $user->isDirty('unidad_organica_id')) {
            return;
        }

        $this->resincronizar($user);
    }

    public function resincronizar(User $user, ?UnidadOrganica $unidad = null): void
    {
        if (! $user->unidad_organica_id) {
            $unidad = null;
        } else {
            $unidad ??= UnidadOrganica::find($user->unidad_organica_id);
        }

        [$user->jefe_inmediato_id, $user->jefe_area_id] = $unidad
            ? $unidad->jefaturasDe($user)
            : [null, null];
    }

    public function saved(User $user): void
    {

        if ($user->wasChanged('jefe_inmediato_id')) {
            app(PapeletasAbiertasService::class)->reasignarDeTrabajador($user);
        }

        if (! $user->wasChanged('sede_id')) {
            return;
        }

        Turno::where('user_id', $user->id)
            ->where('fecha', '>=', now()->toDateString())
            ->update(['sede_id' => $user->sede_id]);

        HorarioActualizado::notificar($user->id);
    }
}
