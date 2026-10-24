<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Motivo;
use App\Models\Papeleta;
use Illuminate\Contracts\View\View;

class PapeletaController extends Controller
{
    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('rrhh.papeletas.show', [
            'papeleta' => $papeleta,
            'historialMes' => [],
            'soloLectura' => true,
            'motivosActivos' => Motivo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
