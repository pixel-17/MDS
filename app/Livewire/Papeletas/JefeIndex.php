<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Livewire\Concerns\PaginaListasDeBandeja;
use App\Models\Papeleta;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Bandeja de Jefe')]
class JefeIndex extends Component
{
    use EscuchaNotificacionesEnVivo, PaginaListasDeBandeja, WithPagination;

    public string $buscar = '';

    /** @return array<int, string> */
    protected function nombresDePaginadores(): array
    {
        return [
            'porDecidirPage',
            'observadasPorMiPage',
            'observacionesRrhhPage',
            'posthocPorResponderPage',
            'enCursoPage',
            'delTurnoPage',
        ];
    }

    public function render(): View
    {
        $user = Auth::user();

        abort_unless($user->esJefeDeEquipo(), 403);

        $termino = trim($this->buscar);
        $filtroBuscar = fn ($query) => $query->whereHas('trabajador', fn ($q) => $q
            ->where(fn ($w) => $w
                ->where('name', 'like', "%{$termino}%")
                ->orWhere('apellido', 'like', "%{$termino}%")
                ->orWhere('dni', 'like', "%{$termino}%")));

        $bases = [
            'porDecidir' => fn () => Papeleta::whereState('estado', PendienteJefe::class)
                ->deJefeInmediato($user),

            'observadasPorMi' => fn () => Papeleta::whereState('estado', ObservadaPorJefe::class)
                ->deJefeInmediato($user),
            'observacionesRrhh' => fn () => Papeleta::whereState('estado', ObservadaPorRrhh::class)
                ->deJefeInmediato($user),

            'posthocPorResponder' => fn () => Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
                ->where('revision_posthoc_estado', 'observada')
                ->where('resuelto_por_jefe_id', $user->id),
            'enCurso' => fn () => Papeleta::whereState('estado', AutorizadaYCorriendo::class)
                ->deJefeInmediato($user),
        ];

        $listar = fn ($consulta, string $nombre, array $relaciones = ['trabajador', 'motivo']) => $this->paginarLista(
            $consulta
                ->when($termino !== '', $filtroBuscar)
                ->with($relaciones)
                ->latest()
                ->orderByDesc('papeletas.id'),
            $nombre.'Page',
        );

        $porDecidir = $listar($bases['porDecidir'](), 'porDecidir');
        $observadasPorMi = $listar($bases['observadasPorMi'](), 'observadasPorMi');
        $observacionesRrhh = $listar($bases['observacionesRrhh'](), 'observacionesRrhh');
        $posthocPorResponder = $listar($bases['posthocPorResponder'](), 'posthocPorResponder');
        $enCurso = $listar($bases['enCurso'](), 'enCurso');

        $delTurnoConsulta = Papeleta::deJefeInmediato($user)->delTurnoVigente();

        foreach ($bases as $base) {
            $delTurnoConsulta->whereNotIn('papeletas.id', $base()->select('papeletas.id'));
        }

        $delTurno = $listar($delTurnoConsulta, 'delTurno');

        return view('livewire.papeletas.jefe-index', compact(
            'porDecidir',
            'observadasPorMi',
            'observacionesRrhh',
            'posthocPorResponder',
            'enCurso',
            'delTurno',
        ));
    }
}
