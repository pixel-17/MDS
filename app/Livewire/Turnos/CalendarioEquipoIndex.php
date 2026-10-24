<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\EquipoDelJefeService;
use App\Services\GeneradorTurnoMensualService;
use App\Services\ProgramacionTurnoService;
use App\Support\PatronDescansoSemanal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Calendario de turnos — mi equipo')]
class CalendarioEquipoIndex extends Component
{
    public int $anio;

    public int $mes;

    #[Locked]
    public ?int $jefeId = null;

    #[Locked]
    public int $version = 0;

    /** @var array<int, string> */
    #[Locked]
    public array $advertencias = [];

    #[Locked]
    public ?string $mensaje = null;

    public array $diasSemanaDescanso276 = [6, 7];

    public function mount(?int $jefeId = null): void
    {
        if ($jefeId !== null) {
            abort_unless(auth()->user()?->hasRole('admin'), 403);
            $this->jefeId = $jefeId;
        }

        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    private function jefeVista(): User
    {
        if ($this->jefeId === null) {
            return auth()->user();
        }

        abort_unless(auth()->user()?->hasRole('admin'), 403);

        return User::findOrFail($this->jefeId);
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

    private function irA(Carbon $fecha): void
    {
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
        $this->advertencias = [];
        $this->mensaje = null;
        $this->version++;
    }

    public function guardar(ProgramacionTurnoService $servicio, array $cambios): void
    {
        $this->mensaje = null;

        if ($cambios === []) {
            $this->advertencias = [];
            $this->mensaje = 'No hay cambios que guardar.';

            return;
        }

        $equipo728 = $this->equipo728();

        $servicio->validarEquipo($equipo728, $this->anio, $this->mes, $cambios);

        $this->advertencias = $servicio->advertenciasEquipo($equipo728, $cambios);

        if ($this->advertencias === []) {
            $this->persistir($servicio, $equipo728, $cambios);
        }
    }

    /**
     * @param  array<int|string, array<string, string>>  $cambios
     */
    public function guardarIgualmente(ProgramacionTurnoService $servicio, array $cambios): void
    {
        $this->persistir($servicio, $this->equipo728(), $cambios);
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
        $this->mensaje = 'Turnos guardados para '.count($cambios).' '.(count($cambios) === 1 ? 'trabajador' : 'trabajadores').'.';
        $this->version++;
    }

    private function equipo728(): Collection
    {
        [$trabajadores, , $soloLectura] = app(EquipoDelJefeService::class)->para($this->jefeVista());

        return $trabajadores
            ->filter(fn (User $t) => $t->regimen === '728' && $t->activo && ! in_array($t->id, $soloLectura, true))
            ->keyBy('id');
    }

    private function equipo276(): Collection
    {
        [$trabajadores, , $soloLectura] = app(EquipoDelJefeService::class)->para($this->jefeVista());

        return $trabajadores
            ->filter(fn (User $t) => $t->regimen === '276' && $t->activo && ! in_array($t->id, $soloLectura, true))
            ->keyBy('id');
    }

    private function proximosDescansos276(Collection $configs276): array
    {
        $hoy = now()->startOfDay();

        return $configs276->mapWithKeys(function (ConfiguracionTurno $config) use ($hoy) {
            $ciclo = $config->dias_trabajo + $config->dias_descanso;
            $dia = $hoy->copy();

            for ($i = 0; $i <= $ciclo; $i++) {
                if (GeneradorTurnoMensualService::esDescansoEnFecha($config->fecha_ancla, $dia, $config->dias_trabajo, $config->dias_descanso)) {
                    return [$config->user_id => $dia->copy()];
                }

                $dia->addDay();
            }

            return [$config->user_id => null];
        })->all();
    }

    public function aplicarDescansoSemana276(GeneradorTurnoMensualService $generador): void
    {
        $this->resetErrorBag('diasSemanaDescanso276');

        $equipo276 = $this->equipo276();

        if ($equipo276->isEmpty()) {
            return;
        }

        $resultado = PatronDescansoSemanal::anclaYCiclo($this->diasSemanaDescanso276, now());

        if ($resultado === null) {
            $this->addError(
                'diasSemanaDescanso276',
                'Elige entre 1 y 6 días de descanso consecutivos dentro de la semana (ej. sábado y domingo).'
            );

            return;
        }

        foreach ($equipo276 as $trabajador) {
            if (! auth()->user()->puedeGestionarTurnoDe($trabajador)) {
                continue;
            }

            $generador->cargarConfiguracion(
                trabajador: $trabajador,
                turno: ConfiguracionTurno::TURNO_276,
                fechaAncla: $resultado['fecha_ancla'],
                actor: auth()->user(),
                diasTrabajo: $resultado['dias_trabajo'],
                diasDescanso: $resultado['dias_descanso'],
            );
        }

        $this->mensaje = 'Días de descanso aplicados a '.$equipo276->count().' '
            .($equipo276->count() === 1 ? 'trabajador de régimen 276.' : 'trabajadores de régimen 276.');
        $this->version++;
    }

    public function render(): View
    {
        $jefe = $this->jefeVista();

        [$trabajadores, $esJefeDeArea, $soloLectura] = app(EquipoDelJefeService::class)->para($jefe);

        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();
        $dias = collect(range(1, $finMes->day));
        $fechas = collect(range(1, $finMes->day))->map(fn (int $d) => $inicioMes->copy()->day($d));

        $turnosPorUsuario = Turno::whereIn('user_id', $trabajadores->pluck('id'))
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->with('sede')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($turnos) => $turnos->keyBy(fn (Turno $turno) => $turno->fecha->day));

        $equipo728 = $this->equipo728();
        $validos728 = [...ConfiguracionTurno::TURNOS_728, ProgramacionTurnoService::DESCANSO];

        $diasIniciales = [];
        foreach ($equipo728 as $trabajador) {
            $mapa = ($turnosPorUsuario->get($trabajador->id) ?? collect())
                ->mapWithKeys(fn (Turno $t) => [$t->fecha->toDateString() => $t->codigo()])
                ->filter(fn (string $codigo) => in_array($codigo, $validos728, true))
                ->all();

            $diasIniciales[(string) $trabajador->id] = (object) $mapa;
        }

        $generador = app(GeneradorTurnoMensualService::class);
        $horas = [];
        foreach (ConfiguracionTurno::TURNOS_728 as $codigo) {
            [$inicio, $fin] = $generador->horasDe($codigo);
            $horas[$codigo] = substr($inicio, 0, 5).' – '.substr($fin, 0, 5);
        }

        $equipo276 = $this->equipo276();
        $configs276 = ConfiguracionTurno::whereIn('user_id', $equipo276->keys())->get()->keyBy('user_id');

        return view('livewire.turnos.calendario-equipo-index', [
            'proximosDescansos276' => $this->proximosDescansos276($configs276),
            'jefe' => $jefe,
            'vistaAdmin' => $this->jefeId !== null,
            'trabajadores' => $trabajadores,
            'esJefeDeArea' => $esJefeDeArea,
            'soloLectura' => $soloLectura,
            'idsJefes' => app(EquipoDelJefeService::class)->idsDeJefes()->all(),
            'dias' => $dias,
            'fechas' => $fechas,
            'inicioMes' => $inicioMes,
            'turnosPorUsuario' => $turnosPorUsuario,
            'hoy' => now(),
            'uids728' => $equipo728->map(fn (User $t) => (string) $t->id)->values()->all(),
            'fechasIso' => $fechas->map(fn (Carbon $f) => $f->toDateString())->values()->all(),
            'diasIniciales' => (object) $diasIniciales,
            'horas' => $horas,
            'equipo276' => $equipo276,
            'configs276' => $configs276,
        ]);
    }
}
