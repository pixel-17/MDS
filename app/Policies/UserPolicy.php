<?php

namespace App\Policies;

use App\Actions\Organigrama\ReglasOrganigrama;
use App\Models\User;

class UserPolicy
{

    public function crearEnUnidad(User $creator, int $unidadDestinoId): bool
    {
        if ($creator->hasRole('admin')) {
            return true;
        }

        if ($this->esJefeDeAreaDe($creator, $unidadDestinoId)) {
            return true;
        }

        return $creator->unidadesQueEncabeza()->whereKey($unidadDestinoId)->exists();
    }

    public function crearTrabajadorPropio(User $creator): bool
    {

        return $creator->esJefeDeEquipo();
    }

    public function puedeCrearAlgo(User $creator): bool
    {

        if ($creator->hasRole('admin')) {
            return false;
        }

        return $creator->unidadesQueEncabeza()->exists()
            || $this->crearTrabajadorPropio($creator);
    }

    public function editar(User $editor, User $target): bool
    {
        if ($editor->hasRole('admin')) {
            return true;
        }

        if ($target->hasAnyRole(['admin', 'rrhh']) || $editor->id === $target->id) {
            return false;
        }

        if ($target->unidad_organica_id
            && $this->esJefeDeAreaDe($editor, $target->unidad_organica_id)) {
            return true;
        }

        return $editor->esJefeInmediatoDe($target) && ! $target->esJefeTitular();
    }

    public function view(User $viewer, User $target): bool
    {
        if ($viewer->hasRole('admin') || $viewer->hasRole('rrhh')) {
            return true;
        }

        if ($viewer->id === $target->id) {
            return true;
        }

        if ($target->unidad_organica_id
            && $this->esJefeDeAreaDe($viewer, $target->unidad_organica_id)) {
            return true;
        }

        return $viewer->esJefeInmediatoDe($target);
    }

    protected function esJefeDeAreaDe(User $user, int $unidadId): bool
    {
        return app(ReglasOrganigrama::class)->esJefeDeAreaDe($user, $unidadId);
    }
}
