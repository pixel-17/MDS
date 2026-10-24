<?php

namespace App\Livewire\Reportes;

use App\Exports\HistorialTrabajadorExport;
use App\Livewire\Concerns\RestringeAReportes;
use App\Models\User;
use App\Services\HistorialTrabajadorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
#[Title('Historial por trabajador')]
class TrabajadorHistorialIndex extends Component
{
    use RestringeAReportes;

    public string $buscar = '';

    #[Url]
    public ?int $trabajadorId = null;

    public function mount(): void
    {
        $this->autorizarAccesoAReportes();
    }

    public function elegir(int $trabajadorId): void
    {
        $this->trabajadorId = $trabajadorId;
        $this->buscar = '';
    }

    public function quitar(): void
    {
        $this->trabajadorId = null;
    }

    public function exportar(HistorialTrabajadorService $service)
    {
        /** @var User $user */
        $user = Auth::user();

        $trabajador = $this->trabajadorId ? User::find($this->trabajadorId) : null;

        abort_unless($trabajador && $service->puedeVer($user, $trabajador), 403);

        return Excel::download(
            new HistorialTrabajadorExport($service->historial($trabajador)),
            'historial-papeletas-'.Str::slug($trabajador->nombre_completo).'.xlsx',
        );
    }

    public function render(HistorialTrabajadorService $service): View
    {
        /** @var User $user */
        $user = Auth::user();

        $trabajador = null;
        $historial = collect();
        $resumen = null;

        if ($this->trabajadorId) {
            $candidato = User::find($this->trabajadorId);

            if ($candidato && $service->puedeVer($user, $candidato)) {
                $trabajador = $candidato;
                $historial = $service->historial($trabajador);
                $resumen = $service->resumen($historial);
            } else {
                $this->trabajadorId = null;
            }
        }

        return view('livewire.reportes.trabajador-historial-index', [
            'sugerencias' => $trabajador ? collect() : $service->trabajadoresVisibles($user, $this->buscar),
            'trabajador' => $trabajador,
            'historial' => $historial,
            'resumen' => $resumen,
        ]);
    }
}
