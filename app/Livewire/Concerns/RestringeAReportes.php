<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait RestringeAReportes
{
    protected function autorizarAccesoAReportes(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole('admin') && ! $user->hasRole('rrhh') && ! $user->can('crearTrabajadorPropio', User::class)) {
            $this->redirectRoute('trabajador.papeletas.index');
        }
    }
}
