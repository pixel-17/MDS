<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Configurar turno')]
class ConfiguracionTurnoForm extends Component
{
    #[Locked]
    public User $trabajador;

    #[Locked]
    public bool $embebido = false;

    #[Locked]
    public bool $sinTurnoVigenteHoy = false;

    public ?string $mensajeExito = null;

    public function mount(User $trabajador, bool $embebido = false): void
    {
        abort_unless((bool) auth()->user()?->puedeGestionarTurnoDe($trabajador), 403);
        abort_unless($trabajador->regimen === '276', 403, 'Esta pantalla es solo para régimen 276; el 728 se programa día por día en turnos.programacion.');

        $this->trabajador = $trabajador;
        $this->embebido = $embebido;

        $this->sinTurnoVigenteHoy = Turno::vigenteParaUsuario($trabajador->id) === null;
    }

    public function guardar(GeneradorTurnoMensualService $generador): void
    {
        abort_unless((bool) auth()->user()?->puedeGestionarTurnoDe($this->trabajador), 403);

        $this->mensajeExito = null;

        $actor = auth()->user();

        $ciclo = GeneradorTurnoMensualService::cicloOrdinario276();

        $generador->cargarConfiguracion(
            trabajador: $this->trabajador,
            turno: ConfiguracionTurno::TURNO_276,
            fechaAncla: $ciclo['fecha_ancla'],
            actor: $actor,
            diasTrabajo: $ciclo['dias_trabajo'],
            diasDescanso: $ciclo['dias_descanso'],
        );

        if ($this->embebido) {
            $this->dispatch('turno-guardado', trabajadorId: $this->trabajador->id);

            return;
        }

        $this->sinTurnoVigenteHoy = Turno::vigenteParaUsuario($this->trabajador->id) === null;
        $this->mensajeExito = 'Configuración de turno cargada. Se generó el horario del mes en curso.';
    }

    public function cancelar(): void
    {
        if ($this->embebido) {
            $this->dispatch('turno-cancelado');
        }
    }

    /**
     * @return array{inicioMes: Carbon, semanas: array<int, array<int, int|null>>, turnosPorDia: Collection<int, Turno>, hoy: Carbon}
     */
    private function calendarioDelMes(): array
    {
        $inicioMes = now()->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $turnosPorDia = Turno::where('user_id', $this->trabajador->id)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get()
            ->keyBy(fn (Turno $turno) => $turno->fecha->day);

        $primerDiaSemana = $inicioMes->dayOfWeekIso;
        $celdas = array_fill(0, $primerDiaSemana - 1, null);

        for ($dia = 1; $dia <= $finMes->day; $dia++) {
            $celdas[] = $dia;
        }

        while (count($celdas) % 7 !== 0) {
            $celdas[] = null;
        }

        return [
            'inicioMes' => $inicioMes,
            'semanas' => array_chunk($celdas, 7),
            'turnosPorDia' => $turnosPorDia,
            'hoy' => now(),
        ];
    }

    public function render(): View
    {
        return view('livewire.turnos.configuracion-turno-form', $this->calendarioDelMes());
    }
}
