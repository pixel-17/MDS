<?php

namespace App\Livewire\Configuraciones;

use App\Livewire\Concerns\RequiereAdmin;
use App\Models\Configuracion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Editar configuración')]
class ConfiguracionForm extends Component
{
    use RequiereAdmin;

    #[Locked]
    public Configuracion $configuracion;

    public string $valor = '';

    /**
     * @var array<int, string>
     */
    public array $diasSeleccionados = [];

    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public static function esClaveDeTurno(string $clave): bool
    {
        return (bool) preg_match('/^(TURNO_[A-Z]+_HORA|HORARIO_ORDINARIO_HORA)_(INICIO|FIN)$/', $clave);
    }

    public function mount(Configuracion $configuracion): void
    {
        abort_if(self::esClaveDeTurno($configuracion->clave), 404);

        $this->configuracion = $configuracion;
        $this->valor = $configuracion->valor;

        if ($this->tipoCampo() === 'dias') {
            $this->diasSeleccionados = array_filter(explode(',', $configuracion->valor), fn ($d) => $d !== '');
        }
    }

    public function tipoCampo(): string
    {
        return self::tipoCampoDe($this->configuracion->clave);
    }

    public static function tipoCampoDe(string $clave): string
    {
        if (preg_match('/(_HORA_(INICIO|FIN)|^BLOQUE_ALMUERZO_(INICIO|FIN))$/', $clave)) {
            return 'hora';
        }

        if ($clave === 'HORARIO_ORDINARIO_DIAS_LABORABLES') {
            return 'dias';
        }

        if (preg_match('/(_MINUTOS|_HORAS_HABILES|_DIAS_HABILES|^TOPE_|^POSTHOC_ALERTA_)/', $clave)) {
            return 'entero';
        }

        return 'texto';
    }

    public function updatedDiasSeleccionados(): void
    {
        $dias = array_unique(array_map('intval', $this->diasSeleccionados));
        sort($dias);

        $this->valor = implode(',', $dias);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return ['valor' => self::reglasParaClave($this->configuracion->clave)];
    }

    /**
     * @return array<int, string>
     */
    public static function reglasParaClave(string $clave): array
    {
        return match (self::tipoCampoDe($clave)) {
            'hora' => ['required', 'string', 'date_format:H:i'],
            'dias' => ['required', 'string', 'regex:/^[1-7](,[1-7])*$/'],
            'entero' => ['required', 'integer', 'min:1', 'max:100000'],
            default => ['required', 'string', 'max:255'],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'valor.date_format' => 'Usa el formato de hora HH:MM de 24 horas, por ejemplo 07:45.',
            'valor.regex' => 'Usa los números de día del 1 (lunes) al 7 (domingo) separados por coma, por ejemplo 1,2,3,4,5.',
        ];
    }

    public function guardar(): void
    {
        $this->autorizarAdmin();

        $datos = $this->validate();

        $this->configuracion->update($datos);

        session()->flash('mensaje', 'Configuración actualizada.');

        $this->redirectRoute('configuraciones.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.configuraciones.configuracion-form');
    }
}
