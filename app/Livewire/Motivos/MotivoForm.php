<?php

namespace App\Livewire\Motivos;

use App\Livewire\Concerns\RequiereAdmin;
use App\Models\Motivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Motivo')]
class MotivoForm extends Component
{
    use RequiereAdmin;

    #[Locked]
    public ?Motivo $motivo = null;

    public string $nombre = '';

    public bool $activo = true;

    public bool $requiereJustificacion = false;

    public bool $aplicaDescuento = false;

    public ?int $plazoJustificacionHorasHabiles = null;

    public function mount(?Motivo $motivo = null): void
    {
        if ($motivo?->exists) {
            $this->motivo = $motivo;
            $this->nombre = $motivo->nombre;
            $this->activo = $motivo->activo;
            $this->requiereJustificacion = $motivo->consecuenciaAlTerminar() === 'justificar';
            $this->aplicaDescuento = $motivo->consecuenciaAlTerminar() === 'descuenta';
            $this->plazoJustificacionHorasHabiles = $motivo->plazo_justificacion_horas_habiles;
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['boolean'],
            'requiereJustificacion' => ['boolean'],
            'aplicaDescuento' => ['boolean'],
            'plazoJustificacionHorasHabiles' => ['nullable', 'integer', 'min:1', 'max:720'],
        ];
    }

    private function generarCodigoDesde(string $nombre): string
    {
        $base = Str::of($nombre)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_');
        $base = $base->isEmpty() ? 'MOTIVO' : (string) $base;

        $codigo = $base;
        $sufijo = 1;

        while (
            Motivo::where('codigo', $codigo)
                ->when($this->motivo, fn ($query) => $query->whereKeyNot($this->motivo->id))
                ->exists()
        ) {
            $codigo = $base.'_'.(++$sufijo);
        }

        return $codigo;
    }

    public function guardar(): void
    {
        $this->autorizarAdmin();

        $datos = $this->validate();

        $requiere = $datos['requiereJustificacion'];

        $atributos = [
            'codigo' => $this->motivo ? $this->motivo->codigo : $this->generarCodigoDesde($datos['nombre']),
            'nombre' => $datos['nombre'],
            'activo' => $datos['activo'],
            'requiere_sustento_en_retorno' => $requiere,

            'suma_descuento' => ! $requiere && $datos['aplicaDescuento'],
            'plazo_justificacion_horas_habiles' => $requiere ? ($datos['plazoJustificacionHorasHabiles'] ?? null) : null,
        ];

        $this->motivo
            ? $this->motivo->update($atributos)
            : Motivo::create($atributos);

        session()->flash('mensaje', $this->motivo ? 'Motivo actualizado.' : 'Motivo creado.');

        $this->redirectRoute('motivos.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.motivos.motivo-form');
    }
}
