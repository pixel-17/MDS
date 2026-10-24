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

    public static function esClaveDeTurno(string $clave): bool
    {
        return (bool) preg_match('/^(TURNO_[A-Z]+_HORA|HORARIO_ORDINARIO_HORA)_(INICIO|FIN)$/', $clave);
    }

    public function mount(Configuracion $configuracion): void
    {
        abort_if(self::esClaveDeTurno($configuracion->clave), 404);

        $this->configuracion = $configuracion;
        $this->valor = $configuracion->valor;
    }

    protected function rules(): array
    {
        return ['valor' => self::reglasParaClave($this->configuracion->clave)];
    }

    public static function reglasParaClave(string $clave): array
    {
        if (preg_match('/(_HORA_(INICIO|FIN)|^BLOQUE_ALMUERZO_(INICIO|FIN))$/', $clave)) {
            return ['required', 'string', 'date_format:H:i'];
        }

        if ($clave === 'HORARIO_ORDINARIO_DIAS_LABORABLES') {
            return ['required', 'string', 'regex:/^[1-7](,[1-7])*$/'];
        }

        if (preg_match('/(_MINUTOS|_HORAS_HABILES|_DIAS_HABILES|^TOPE_|^POSTHOC_ALERTA_)/', $clave)) {
            return ['required', 'integer', 'min:1', 'max:100000'];
        }

        return ['required', 'string', 'max:255'];
    }

    /** @return array<string, string> */
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
