<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\EquipoDelJefeService;
use App\Services\GeneradorTurnoMensualService;
use App\Services\ProgramacionTurnoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Programar turnos — mi equipo (728)')]
class ProgramacionEquipo extends Component
{
    public int $anio;

    public int $mes;

    #[Locked]
    public int $version = 0;

    /** @var array<int, string> */
    #[Locked]
    public array $advertencias = [];

    #[Locked]
    public ?string $mensaje = null;

    public function mount(): void
    {
        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    public function mesAnterior(): void
    {
        $this->irA(Carbon::create($this->anio, $this->mes, 1)->subMonthNoOverflow());
    }

    public function mesSiguiente(): void
    {
        $this->irA(Carbon::create($this->anio, $this->mes, 1)->addMonthNoOverflow());
    }

    public function irAHoy(): void
    {
        $this->irA(now());
    }

    public function guardar(ProgramacionTurnoService $servicio, array $cambios): void
    {
        $this->mensaje = null;

        if ($cambios === []) {
            $this->advertencias = [];
            $this->mensaje = 'No hay cambios que guardar.';

            return;
        }

        $equipo = $this->equipo();

        $servicio->validarEquipo($equipo, $this->anio, $this->mes, $cambios);

        $this->advertencias = $servicio->advertenciasEquipo($equipo, $cambios);

        if ($this->advertencias === []) {
            $this->persistir($servicio, $equipo, $cambios);
        }
    }

    /**
     * @param  array<int|string, array<string, string>>  $cambios
     */
    public function guardarIgualmente(ProgramacionTurnoService $servicio, array $cambios): void
    {
        $this->persistir($servicio, $this->equipo(), $cambios);
    }

    public function descartarAdvertencias(): void
    {
        $this->advertencias = [];
    }

    /**
     * @param  Collection<int, User>  $equipo
     * @param  array<int|string, array<string, string>>  $cambios
     */
    private function persistir(ProgramacionTurnoService $servicio, Collection $equipo, array $cambios): void
    {
        $servicio->guardarEquipo($equipo, $this->anio, $this->mes, $cambios, auth()->user());

        $this->advertencias = [];
        $this->mensaje = 'Programación de '.Carbon::create($this->anio, $this->mes, 1)->translatedFormat('F Y')
            .' guardada para '.count($cambios).' '.(count($cambios) === 1 ? 'trabajador' : 'trabajadores').'.';
        $this->version++;
    }

    private function irA(Carbon $fecha): void
    {
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
        $this->advertencias = [];
        $this->mensaje = null;
        $this->version++;
    }

    private function equipo(): Collection
    {
        [$trabajadores, , $soloLectura] = app(EquipoDelJefeService::class)->para(auth()->user());

        return $trabajadores
            ->filter(fn (User $t) => $t->regimen === '728' && $t->activo && ! in_array($t->id, $soloLectura, true))
            ->keyBy('id');
    }

    public function render(): View
    {
        [$todos, $esJefeDeArea, $soloLectura] = app(EquipoDelJefeService::class)->para(auth()->user());

        $todos = $todos->reject(fn (User $t) => in_array($t->id, $soloLectura, true))->values();

        $equipo = $todos->filter(fn (User $t) => $t->regimen === '728' && $t->activo)->values();

        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();
        $validos = [...ConfiguracionTurno::TURNOS_728, ProgramacionTurnoService::DESCANSO];

        $turnosPorUsuario = Turno::whereIn('user_id', $equipo->pluck('id'))
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get()
            ->groupBy('user_id');

        $diasIniciales = [];
        foreach ($equipo as $trabajador) {
            $mapa = ($turnosPorUsuario->get($trabajador->id) ?? collect())
                ->mapWithKeys(fn (Turno $t) => [$t->fecha->toDateString() => $t->codigo()])
                ->filter(fn (string $codigo) => in_array($codigo, $validos, true))
                ->all();

            $diasIniciales[(string) $trabajador->id] = (object) $mapa;
        }

        $fechas = collect(range(1, $finMes->day))->map(fn (int $d) => $inicioMes->copy()->day($d));

        $generador = app(GeneradorTurnoMensualService::class);
        $horas = [];
        foreach (ConfiguracionTurno::TURNOS_728 as $codigo) {
            [$inicio, $fin] = $generador->horasDe($codigo);
            $horas[$codigo] = substr($inicio, 0, 5).' – '.substr($fin, 0, 5);
        }

        return view('livewire.turnos.programacion-equipo', [
            'equipo' => $equipo,
            'esJefeDeArea' => $esJefeDeArea,
            'sinProgramacionDiaria' => $todos->count() - $equipo->count(),
            'inicioMes' => $inicioMes,
            'fechas' => $fechas,
            'fechasIso' => $fechas->map(fn (Carbon $f) => $f->toDateString())->values()->all(),
            'uids' => $equipo->map(fn (User $t) => (string) $t->id)->values()->all(),
            'diasIniciales' => (object) $diasIniciales,
            'horas' => $horas,
            'hoy' => now()->toDateString(),
        ]);
    }
}
