<?php

namespace App\Observers;

use App\Actions\Organigrama\PapeletasAbiertasService;
use App\Models\UnidadOrganica;
use App\Models\User;

class UnidadOrganicaObserver
{
    public function saved(UnidadOrganica $unidad): void
    {
        if (! $unidad->wasChanged(['jefe_id', 'parent_id'])) {
            return;
        }

        $this->resincronizarSubarbol($unidad);
    }

    private function resincronizarSubarbol(UnidadOrganica $unidad): void
    {
        $unidadIds = [$unidad->id, ...$unidad->descendantIds()];

        $arbol = UnidadOrganica::arbolEnMemoria();
        $observer = app(UserObserver::class);
        $reasignar = app(PapeletasAbiertasService::class);

        User::whereIn('unidad_organica_id', $unidadIds)
            ->chunkById(500, function ($usuarios) use ($arbol, $observer, $reasignar) {
                foreach ($usuarios as $usuario) {
                    $observer->resincronizar($usuario, $arbol->get($usuario->unidad_organica_id));
                    $cambioJefe = $usuario->isDirty('jefe_inmediato_id');
                    $usuario->saveQuietly();

                    if ($cambioJefe) {
                        $reasignar->reasignarDeTrabajador($usuario);
                    }
                }
            });
    }
}
