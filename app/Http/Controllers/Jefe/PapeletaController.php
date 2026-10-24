<?php

namespace App\Http\Controllers\Jefe;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use Illuminate\View\View;

class PapeletaController extends Controller
{
    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('jefe.papeletas.show', compact('papeleta'));
    }
}
