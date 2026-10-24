<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Services\BloqueoRevisionService;
use App\Services\ReporteHorasAcumuladasService;
use App\States\Papeleta\Rechazada;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PapeletaController extends Controller
{
    public function show(Request $request, Papeleta $papeleta, ReporteHorasAcumuladasService $reporte, BloqueoRevisionService $bloqueo): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea', 'enRevisionPor']);

        $tomado = $bloqueo->marcar($papeleta, $request->user());
        $enRevisionPor = $tomado ? null : $bloqueo->nombreRevisor($papeleta);

        $historialMes = $this->historialDelMes($request, $papeleta, $reporte);

        $motivosActivos = Motivo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('rrhh.papeletas.show', compact('papeleta', 'historialMes', 'enRevisionPor', 'motivosActivos'));
    }

    private function historialDelMes(Request $request, Papeleta $papeleta, ReporteHorasAcumuladasService $reporte): array
    {
        $mes = $papeleta->dia_operativo->format('Y-m');
        [$inicio, $fin] = $reporte->rangoDelMes($mes);

        $delMes = Papeleta::query()
            ->where('trabajador_id', $papeleta->trabajador_id)
            ->whereKeyNot($papeleta->getKey())
            ->whereBetween('dia_operativo', [$inicio->toDateString(), $fin->toDateString()]);

        $detalle = $reporte->detalle($request->user(), $mes, ['trabajador_id' => $papeleta->trabajador_id])
            ->reject(fn (array $fila) => $fila['papeleta_id'] === $papeleta->getKey());

        return [
            'mes' => $mes,
            'papeletas' => (clone $delMes)->count(),
            'rechazadas' => (clone $delMes)->whereState('estado', Rechazada::class)->count(),
            'observaciones' => (int) (clone $delMes)->sum('contador_observaciones_jefe')
                + (int) (clone $delMes)->sum('contador_observaciones_rrhh'),
            'minutos' => (int) $detalle->sum('minutos'),
            'minutos_con_descuento' => (int) $detalle->where('suma_descuento', true)->sum('minutos'),
        ];
    }
}
