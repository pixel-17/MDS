<?php

namespace App\Livewire\Reportes;

use App\Exports\HorasAcumuladasExport;
use App\Livewire\Concerns\RestringeAReportes;
use App\Models\Motivo;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\ReporteHorasAcumuladasService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
#[Title('Horas acumuladas')]
class HorasAcumuladasIndex extends Component
{
    use RestringeAReportes;

    public string $mes;

    public ?int $trabajadorId = null;

    public string $buscar = '';

    public int $top = 0;

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    public ?int $motivoId = null;

    public ?string $regimen = null;

    public bool $soloConDescuento = false;

    public string $vista = 'resumen';

    public function mount(): void
    {
        $this->autorizarAccesoAReportes();

        $this->mes = now()->format('Y-m');
    }

    protected function filtros(): array
    {
        return [
            'trabajador_id' => $this->trabajadorId,
            'buscar' => $this->buscar,
            'sede_id' => $this->sedeId,
            'unidad_organica_id' => $this->unidadOrganicaId,
            'motivo_id' => $this->motivoId,
            'regimen' => $this->regimen,
            'solo_con_descuento' => $this->soloConDescuento,
        ];
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['trabajadorId', 'buscar', 'top', 'sedeId', 'unidadOrganicaId', 'motivoId', 'regimen', 'soloConDescuento']);
    }

    public function exportar(ReporteHorasAcumuladasService $service)
    {
        $this->validate([
            'mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $detalle = $service->detalle($user, $this->mes, $this->filtros());
        $resumen = $service->resumenPorTrabajador($user, $this->mes, $this->filtros(), $detalle);

        $nombreArchivo = "horas-acumuladas-{$this->mes}.xlsx";

        return Excel::download(new HorasAcumuladasExport($resumen, $detalle), $nombreArchivo);
    }

    public function render(ReporteHorasAcumuladasService $service): View
    {
        /** @var User $user */
        $user = Auth::user();

        $esAdmin = $user->hasRole('admin');
        $esRrhh = $user->hasRole('rrhh');

        $trabajadoresDisponibles = ($esAdmin || $esRrhh)
            ? User::role('trabajador')->orderBy('name')->get(['id', 'name', 'apellido'])
            : $user->trabajadoresParaReportes()->sortBy('name')->values();

        $sedesQuery = Sede::where('activo', true);
        $unidadesQuery = UnidadOrganica::where('activo', true);

        if (! $esAdmin && ! $esRrhh) {
            $sedeIds = $trabajadoresDisponibles->pluck('sede_id')->filter()->unique();
            $unidadIds = $trabajadoresDisponibles->pluck('unidad_organica_id')->filter()->unique();

            $sedesQuery->whereIn('id', $sedeIds);
            $unidadesQuery->whereIn('id', $unidadIds);
        }

        $detalle = $service->detalle($user, $this->mes, $this->filtros());

        return view('livewire.reportes.horas-acumuladas-index', [
            'resumen' => $service->resumenPorTrabajador($user, $this->mes, $this->filtros(), $detalle),
            'detalleDiario' => $service->resumenDiarioPorTrabajador($user, $this->mes, $this->filtros(), $detalle),
            'trabajadoresDisponibles' => $trabajadoresDisponibles,
            'sedes' => $sedesQuery->orderBy('nombre')->get(),
            'unidadesOrganicas' => $unidadesQuery->orderBy('nombre')->get(),
            'motivos' => Motivo::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
