<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use App\Support\PatronDescansoSemanal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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

    public string $turno = '';

    public string $fechaAncla = '';

    public int $diasTrabajo = 6;

    public int $diasDescanso = 1;

    public array $diasSemanaDescanso = [];

    public function mount(User $trabajador): void
    {
        abort_unless(auth()->user()->puedeGestionarTurnoDe($trabajador), 403);

        $this->trabajador = $trabajador;

        $config = ConfiguracionTurno::where('user_id', $trabajador->id)->first();

        if ($config) {
            $this->turno = $config->turno;
            $this->fechaAncla = $config->fecha_ancla->toDateString();
            $this->diasTrabajo = $config->dias_trabajo;
            $this->diasDescanso = $config->dias_descanso;
        } else {

            $opciones = ConfiguracionTurno::turnosValidosPara($trabajador);
            $this->turno = count($opciones) === 1 ? $opciones[0] : '';
            $this->fechaAncla = now()->toDateString();
        }
    }

    protected function rules(): array
    {
        return [
            'turno' => ['required', 'in:'.implode(',', ConfiguracionTurno::turnosValidosPara($this->trabajador))],
            'fechaAncla' => ['required', 'date'],
            'diasTrabajo' => ['required', 'integer', 'min:1', 'max:30'],
            'diasDescanso' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    protected $messages = [
        'turno.in' => 'Ese turno no existe para el régimen de este trabajador.',
    ];

    public function aplicarDiasSemana(): void
    {
        $referencia = $this->fechaAncla !== '' ? Carbon::parse($this->fechaAncla) : now();
        $resultado = PatronDescansoSemanal::anclaYCiclo($this->diasSemanaDescanso, $referencia);

        if ($resultado === null) {
            throw ValidationException::withMessages([
                'diasSemanaDescanso' => count($this->diasSemanaDescanso) >= 7 || $this->diasSemanaDescanso === []
                    ? 'Elige entre 1 y 6 días de descanso.'
                    : 'Los días de descanso deben ser consecutivos dentro de la semana (ej. sábado y domingo, o lunes y martes). Para descansos salteados, carga días de trabajo/descanso por número más abajo.',
            ]);
        }

        $this->diasTrabajo = $resultado['dias_trabajo'];
        $this->diasDescanso = $resultado['dias_descanso'];
        $this->fechaAncla = $resultado['fecha_ancla']->toDateString();
    }

    public function guardar(GeneradorTurnoMensualService $generador): void
    {
        abort_unless(auth()->user()->puedeGestionarTurnoDe($this->trabajador), 403);

        $datos = $this->validate();

        $generador->cargarConfiguracion(
            trabajador: $this->trabajador,
            turno: $datos['turno'],
            fechaAncla: Carbon::parse($datos['fechaAncla']),
            actor: auth()->user(),
            diasTrabajo: $datos['diasTrabajo'],
            diasDescanso: $datos['diasDescanso'],
        );

        session()->flash('mensaje', 'Configuración de turno cargada. Se generó el horario del mes en curso.');

        $this->redirectRoute('turnos.index', navigate: false);
    }

    private function previsualizacion(): ?array
    {
        if ($this->fechaAncla === '' || $this->diasTrabajo < 1 || $this->diasDescanso < 1) {
            return null;
        }

        try {
            $ancla = Carbon::parse($this->fechaAncla);
        } catch (\Throwable) {
            return null;
        }

        $inicio = now()->startOfWeek(Carbon::MONDAY);

        return collect(range(0, 13))
            ->map(function (int $i) use ($inicio, $ancla) {
                $dia = $inicio->copy()->addDays($i);

                return [
                    'fecha' => $dia->toDateString(),
                    'etiqueta' => ucfirst($dia->translatedFormat('D d')),
                    'descanso' => GeneradorTurnoMensualService::esDescansoEnFecha($ancla, $dia, $this->diasTrabajo, $this->diasDescanso),
                ];
            })
            ->all();
    }

    public function render(): View
    {
        return view('livewire.turnos.configuracion-turno-form', [
            'opcionesTurno' => ConfiguracionTurno::turnosValidosPara($this->trabajador),
            'previsualizacion' => $this->previsualizacion(),
        ]);
    }
}
