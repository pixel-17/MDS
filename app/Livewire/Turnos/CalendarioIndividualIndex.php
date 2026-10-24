<?php

namespace App\Livewire\Turnos;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Calendario de turnos')]
class CalendarioIndividualIndex extends Component
{
    #[Locked]
    public User $trabajador;

    public int $anio;

    public int $mes;

    public function mount(?User $trabajador = null): void
    {
        $actor = auth()->user();

        if ($trabajador === null) {
            $this->trabajador = $actor;
        } else {
            abort_unless(
                $actor->hasRole('admin') || $actor->id === $trabajador->id || $actor->puedeGestionarTurnoDe($trabajador),
                403
            );
            $this->trabajador = $trabajador;
        }

        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    public function getListeners(): array
    {
        if (auth()->id() !== $this->trabajador->id) {
            return [];
        }

        return [
            "echo-private:App.Models.User.{$this->trabajador->id},HorarioActualizado" => 'refrescar',
        ];
    }

    public function refrescar(): void {}

    public function mesAnterior(): void
    {
        $fecha = Carbon::create($this->anio, $this->mes, 1)->subMonthNoOverflow();
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
    }

    public function mesSiguiente(): void
    {
        $fecha = Carbon::create($this->anio, $this->mes, 1)->addMonthNoOverflow();
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
    }

    public function irAHoy(): void
    {
        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    public function render(): View
    {
        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $turnosPorDia = Turno::where('user_id', $this->trabajador->id)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->with('sede')
            ->get()
            ->keyBy(fn (Turno $turno) => $turno->fecha->day);

        $primerDiaSemana = $inicioMes->dayOfWeekIso; // 1 = lunes
        $celdas = array_fill(0, $primerDiaSemana - 1, null);

        for ($dia = 1; $dia <= $finMes->day; $dia++) {
            $celdas[] = $dia;
        }

        while (count($celdas) % 7 !== 0) {
            $celdas[] = null;
        }

        $semanas = array_chunk($celdas, 7);

        $detalleDias = $turnosPorDia->mapWithKeys(fn (Turno $turno, int $dia) => [
            $dia => [
                'titulo' => $turno->nombreTurno(),
                'horario' => (! $turno->es_descanso && $turno->hora_inicio && $turno->hora_fin)
                    ? substr($turno->hora_inicio, 0, 5).' – '.substr($turno->hora_fin, 0, 5)
                    : null,
                'sede' => $turno->sede?->nombre,
            ],
        ])->all();

        return view('livewire.turnos.calendario-individual-index', [
            'inicioMes' => $inicioMes,
            'semanas' => $semanas,
            'turnosPorDia' => $turnosPorDia,
            'detalleDias' => $detalleDias,
            'hoy' => now(),
        ]);
    }
}
