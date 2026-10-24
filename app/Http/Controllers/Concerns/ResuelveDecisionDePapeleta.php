<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\PapeletaException;
use Illuminate\Http\RedirectResponse;

trait ResuelveDecisionDePapeleta
{
    protected function resolverDecision(callable $accion, string $tipoMensaje, string $mensaje): RedirectResponse
    {
        try {
            $accion();
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($tipoMensaje, $mensaje);
    }
}
