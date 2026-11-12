<?php

namespace App\Livewire\Configuraciones;

use App\Livewire\Concerns\RequiereAdmin;
use App\Models\Configuracion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Configuraciones')]
class ConfiguracionIndex extends Component
{
    use RequiereAdmin;

    private const CLAVE_BLOQUE_INICIO = 'BLOQUE_ALMUERZO_INICIO';

    private const CLAVE_BLOQUE_FIN = 'BLOQUE_ALMUERZO_FIN';

    private const ORDEN_GRUPOS = [
        'General',
        'Horario ordinario (régimen 276)',
        'Alertas de revisión post-hoc',
    ];

    public bool $modalAbierto = false;

    public bool $esBloqueAlmuerzo = false;

    public ?int $editandoId = null;

    public string $claveEditando = '';

    public string $descripcionEditando = '';

    public string $valor = '';

    /**
     * @var array<int, string>
     */
    public array $diasSeleccionados = [];

    public string $bloqueInicio = '';

    public string $bloqueFin = '';

    public function render(): View
    {
        $configuraciones = Configuracion::orderBy('clave')->get()
            ->reject(fn (Configuracion $c) => ConfiguracionForm::esClaveDeTurno($c->clave))
            ->reject(fn (Configuracion $c) => in_array($c->clave, [self::CLAVE_BLOQUE_INICIO, self::CLAVE_BLOQUE_FIN], true))
            ->values();

        $grupos = $configuraciones
            ->groupBy(fn (Configuracion $c) => self::grupoDe($c->clave))
            ->sortBy(fn ($_, string $grupo) => array_search($grupo, self::ORDEN_GRUPOS, true));

        return view('livewire.configuraciones.configuracion-index', [
            'grupos' => $grupos,
            'bloqueAlmuerzoInicio' => Configuracion::where('clave', self::CLAVE_BLOQUE_INICIO)->value('valor') ?? '',
            'bloqueAlmuerzoFin' => Configuracion::where('clave', self::CLAVE_BLOQUE_FIN)->value('valor') ?? '',
        ]);
    }

    private static function grupoDe(string $clave): string
    {
        return match (true) {
            str_starts_with($clave, 'HORARIO_ORDINARIO_') => 'Horario ordinario (régimen 276)',
            str_starts_with($clave, 'POSTHOC_ALERTA_') => 'Alertas de revisión post-hoc',
            default => 'General',
        };
    }

    public function editar(int $id): void
    {
        $this->resetErrorBag();

        $configuracion = Configuracion::findOrFail($id);

        $this->editandoId = $configuracion->id;
        $this->claveEditando = $configuracion->clave;
        $this->descripcionEditando = $configuracion->descripcion ?? '';
        $this->valor = $configuracion->valor;
        $this->esBloqueAlmuerzo = false;

        $this->diasSeleccionados = $this->tipoCampoDeClaveEditando() === 'dias'
            ? array_values(array_filter(explode(',', $configuracion->valor), fn ($d) => $d !== ''))
            : [];

        $this->modalAbierto = true;
    }

    public function editarBloqueAlmuerzo(): void
    {
        $this->resetErrorBag();

        $valorInicio = Configuracion::where('clave', self::CLAVE_BLOQUE_INICIO)->value('valor');
        $this->bloqueInicio = is_string($valorInicio) ? $valorInicio : '';

        $valorFin = Configuracion::where('clave', self::CLAVE_BLOQUE_FIN)->value('valor');
        $this->bloqueFin = is_string($valorFin) ? $valorFin : '';
        $this->esBloqueAlmuerzo = true;
        $this->editandoId = null;

        $this->modalAbierto = true;
    }

    public function tipoCampoDeClaveEditando(): string
    {
        return $this->claveEditando !== '' ? ConfiguracionForm::tipoCampoDe($this->claveEditando) : 'texto';
    }

    public function updatedDiasSeleccionados(): void
    {
        $dias = array_unique(array_map('intval', $this->diasSeleccionados));
        sort($dias);

        $this->valor = implode(',', $dias);
    }

    public function updatedModalAbierto(bool $value): void
    {
        if (! $value) {
            $this->resetFormulario();
        }
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    public function guardar(): void
    {
        $this->autorizarAdmin();

        if ($this->esBloqueAlmuerzo) {
            $this->guardarBloqueAlmuerzo();

            return;
        }

        $this->guardarValorUnico();
    }

    private function guardarValorUnico(): void
    {
        $configuracion = Configuracion::findOrFail($this->editandoId);

        $datos = $this->validate([
            'valor' => ConfiguracionForm::reglasParaClave($this->claveEditando),
        ], [
            'valor.date_format' => 'Usa el formato de hora HH:MM de 24 horas, por ejemplo 07:45.',
            'valor.regex' => 'Usa los números de día del 1 (lunes) al 7 (domingo) separados por coma, por ejemplo 1,2,3,4,5.',
        ]);

        $configuracion->update($datos);

        $this->guardadoExitoso('Configuración actualizada.');
    }

    private function guardarBloqueAlmuerzo(): void
    {
        $this->validate([
            'bloqueInicio' => ['required', 'string', 'date_format:H:i'],
            'bloqueFin' => ['required', 'string', 'date_format:H:i', 'after:bloqueInicio'],
        ], [
            'bloqueInicio.date_format' => 'Usa el formato de hora HH:MM de 24 horas, por ejemplo 13:00.',
            'bloqueFin.date_format' => 'Usa el formato de hora HH:MM de 24 horas, por ejemplo 14:00.',
            'bloqueFin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
        ]);

        Configuracion::where('clave', self::CLAVE_BLOQUE_INICIO)->update(['valor' => $this->bloqueInicio]);
        Configuracion::where('clave', self::CLAVE_BLOQUE_FIN)->update(['valor' => $this->bloqueFin]);

        $this->guardadoExitoso('Bloque de almuerzo actualizado.');
    }

    private function guardadoExitoso(string $mensaje): void
    {
        session()->now('mensaje', $mensaje);

        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    private function resetFormulario(): void
    {
        $this->reset([
            'editandoId', 'claveEditando', 'descripcionEditando', 'valor', 'diasSeleccionados',
            'esBloqueAlmuerzo', 'bloqueInicio', 'bloqueFin',
        ]);
    }
}
