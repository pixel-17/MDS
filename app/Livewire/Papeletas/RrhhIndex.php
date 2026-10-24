<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Livewire\Concerns\PaginaListasDeBandeja;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\Sede;
use App\Models\Sustento;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Bandeja de RRHH')]
class RrhhIndex extends Component
{
    use EscuchaNotificacionesEnVivo, PaginaListasDeBandeja, WithPagination;

    public string $buscar = '';

    public ?int $motivoId = null;

    public ?int $sedeId = null;

    public string $regimen = '';

    public string $desde = '';

    public string $hasta = '';

    private const FILTROS = ['motivoId', 'sedeId', 'regimen', 'desde', 'hasta'];

    public function updated(string $nombre): void
    {
        if (in_array($nombre, self::FILTROS, true)) {
            $this->updatedBuscar();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('buscar', ...self::FILTROS);
        $this->updatedBuscar();
    }

    /** @return array<int, string> */
    protected function nombresDePaginadores(): array
    {
        return ['porDecidirPage', 'posthocPage', 'sustentosPage', 'abandonosPage'];
    }

    public function render(): View
    {

        $filtroBuscar = fn ($query) => $query
            ->when($this->buscar, fn ($q) => $q->whereHas('trabajador', fn ($t) => $t
                ->where('name', 'like', "%{$this->buscar}%")
                ->orWhere('apellido', 'like', "%{$this->buscar}%")))
            ->when($this->motivoId, fn ($q) => $q->where('papeletas.motivo_id', $this->motivoId))
            ->when($this->sedeId, fn ($q) => $q->where('papeletas.sede_id', $this->sedeId))
            ->when(in_array($this->regimen, ['276', '728'], true), fn ($q) => $q->where('papeletas.regimen', $this->regimen))
            ->when($this->fechaValida($this->desde), fn ($q) => $q->whereDate('papeletas.dia_operativo', '>=', $this->desde))
            ->when($this->fechaValida($this->hasta), fn ($q) => $q->whereDate('papeletas.dia_operativo', '<=', $this->hasta));

        $porDecidir = $this->paginarLista(
            Papeleta::whereState('estado', PendienteRrhh::class)
                ->when(true, $filtroBuscar)
                ->with(['trabajador', 'motivo'])

                ->oldest()
                ->orderBy('papeletas.id'),
            'porDecidirPage',
        );

        $posthocPendientes = $this->paginarLista(
            Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
                ->whereIn('revision_posthoc_estado', ['pendiente', 'respondida'])
                ->when(true, $filtroBuscar)
                ->with(['trabajador', 'motivo', 'resueltoPorJefe'])
                ->latest()
                ->orderByDesc('papeletas.id'),
            'posthocPage',
        );

        $sustentosPorRevisar = $this->paginarLista(
            Papeleta::whereState('estado', EnJustificacion::class)
                ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
                ->when(true, $filtroBuscar)

                ->with(['trabajador', 'motivo', 'sustentos' => fn ($q) => $q->where('estado', 'presentado')])

                ->orderBy(
                    Sustento::select('fecha_limite')
                        ->whereColumn('sustentos.papeleta_id', 'papeletas.id')
                        ->where('estado', 'presentado')
                        ->orderBy('fecha_limite')
                        ->limit(1)
                )
                ->orderBy('papeletas.id'),
            'sustentosPage',
        );

        $abandonosEnRegularizacion = $this->paginarLista(
            Papeleta::whereState('estado', Finalizada::class)
                ->where('causa_finalizacion_sin_retorno', 'abandono_no_marcado')
                ->where('requiere_visto_bueno', true)
                ->when(true, $filtroBuscar)
                ->with(['trabajador', 'motivo'])
                ->orderBy('regularizacion_fecha_limite')
                ->orderBy('papeletas.id'),
            'abandonosPage',
        );

        $masAntigua = Papeleta::whereState('estado', PendienteRrhh::class)
            ->when(true, $filtroBuscar)
            ->min('papeletas.created_at');

        return view('livewire.papeletas.rrhh-index', [
            'porDecidir' => $porDecidir,
            'posthocPendientes' => $posthocPendientes,
            'sustentosPorRevisar' => $sustentosPorRevisar,
            'abandonosEnRegularizacion' => $abandonosEnRegularizacion,
            'masAntigua' => $masAntigua ? Carbon::parse($masAntigua) : null,
            'motivos' => Motivo::orderBy('nombre')->get(['id', 'nombre']),
            'sedes' => Sede::orderBy('nombre')->get(['id', 'nombre']),
            'hayFiltros' => $this->buscar !== '' || $this->motivoId || $this->sedeId
                || $this->regimen !== '' || $this->desde !== '' || $this->hasta !== '',
        ]);
    }

    private function fechaValida(string $fecha): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
            && checkdate((int) substr($fecha, 5, 2), (int) substr($fecha, 8, 2), (int) substr($fecha, 0, 4));
    }
}
