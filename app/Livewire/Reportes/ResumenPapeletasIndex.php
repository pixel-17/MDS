<?php

namespace App\Livewire\Reportes;

use App\Exports\HorasAcumuladasExport;
use App\Livewire\Concerns\RestringeAReportes;
use App\Models\Motivo;
use App\Models\Sede;
use App\Models\User;
use App\Services\ReporteHorasAcumuladasService;
use App\Services\ResumenPapeletasService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
#[Title('Resumen de papeletas')]
class ResumenPapeletasIndex extends Component
{
    use RestringeAReportes;

    #[Url(as: 'tab', keep: false)]
    public string $tab = '';

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

        if ($this->tab === '' || $this->tab === 'resumen') {
            $this->tab = 'horas';
        }

        $this->normalizarTab();
    }

    public function cambiarTab(string $tab): void
    {
        $this->tab = $tab;

        $this->normalizarTab();
    }

    private function normalizarTab(): void
    {
        if ($this->tab === 'resumen' && ! $this->puedeVerResumen()) {
            $this->tab = 'horas';
        }
    }

    private function puedeVerResumen(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->hasRole('admin') || $user->hasRole('rrhh');
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

    #[Computed]
    public function trabajadoresDisponibles(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $this->puedeVerResumen()
            ? User::role('trabajador')->orderBy('name')->get(['id', 'name', 'apellido'])
            : $user->trabajadoresParaReportes()->sortBy('name')->values();
    }

    #[Computed]
    public function sedes(): Collection
    {
        $query = Sede::where('activo', true);

        if (! $this->puedeVerResumen()) {
            $query->whereIn('id', $this->trabajadoresDisponibles->pluck('sede_id')->filter()->unique());
        }

        return $query->orderBy('nombre')->get();
    }

    #[Computed]
    public function motivos(): Collection
    {
        return Motivo::where('activo', true)->orderBy('nombre')->get();
    }

    public function render(ReporteHorasAcumuladasService $horasService, ResumenPapeletasService $resumenService): View
    {
        $resumenPapeletas = null;
        $resumenHoras = null;
        $detalleDiario = null;

        if ($this->tab === 'resumen') {
            $resumenPapeletas = $resumenService->resumen($this->mes);
        } else {
            /** @var User $user */
            $user = Auth::user();

            $detalle = $horasService->detalle($user, $this->mes, $this->filtros());
            $resumenHoras = $horasService->resumenPorTrabajador($user, $this->mes, $this->filtros(), $detalle);
            $detalleDiario = $horasService->resumenDiarioPorTrabajador($user, $this->mes, $this->filtros(), $detalle);
        }

        return view('livewire.reportes.resumen-papeletas-index', [
            'puedeVerResumen' => $this->puedeVerResumen(),
            'resumenPapeletas' => $resumenPapeletas,
            'resumen' => $resumenHoras,
            'detalleDiario' => $detalleDiario,
            'sedes' => $this->sedes,
            'motivos' => $this->motivos,
        ]);
    }
}
