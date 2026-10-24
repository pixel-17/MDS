<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Exceptions\UsuarioException;
use Closure;

trait ManejaExcepcionesDeUsuario
{

    protected function intentar(Closure $accion, Closure $alFallar): bool
    {
        try {
            $accion();

            return true;
        } catch (UsuarioException $e) {
            $alFallar($e);

            return false;
        }
    }
}
