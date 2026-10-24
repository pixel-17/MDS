<?php

namespace App\Livewire\Reportes;

use App\Livewire\Concerns\RestringeAReportes;
use App\Models\User;
use App\Services\ReporteSustentosService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Sustentos')]
class SustentosIndex extends Component
{
    use RestringeAReportes, WithPagination;

    #[Url]
    public ?int $trabajadorId = null;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $estado = '';

    public ?string $desde = null;

    public ?string $hasta = null;

    public function mount(): void
    {
        $this->autorizarAccesoAReportes();
    }

    public function updatingTrabajadorId(): void
    {
        $this->resetPage();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    public function updatingDesde(): void
    {
        $this->resetPage();
    }

    public function updatingHasta(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['trabajadorId', 'buscar', 'estado', 'desde', 'hasta']);
        $this->resetPage();
    }

    public function render(ReporteSustentosService $service): View
    {
        /** @var User $user */
        $user = Auth::user();

        $filtros = [
            'trabajador_id' => $this->trabajadorId,
            'buscar' => $this->buscar,
            'estado' => $this->estado,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ];

        return view('livewire.reportes.sustentos-index', [
            'sustentos' => $service->query($user, $filtros)->paginate(20),
        ]);
    }
}
