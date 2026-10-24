<?php

namespace App\Livewire\Papeletas;

use App\Models\Papeleta;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Abandonos')]
class RrhhAbandonos extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $causa = '';

    public const CAUSAS = [
        'abandono_no_marcado' => 'Abandono no marcado',
        'comision_servicio_campo' => 'Comisión de servicio en campo',
    ];

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedCausa(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {

        $abandonos = Papeleta::where('causa_finalizacion_sin_retorno', 'abandono_no_marcado')
            ->when(array_key_exists($this->causa, self::CAUSAS), fn ($q) => $q->where('causa_finalizacion_sin_retorno', $this->causa))
            ->when($this->buscar, fn ($q) => $q->whereHas('trabajador', fn ($t) => $t
                ->where('name', 'like', "%{$this->buscar}%")
                ->orWhere('apellido', 'like', "%{$this->buscar}%")))
            ->with([
                'trabajador',
                'motivo',
                'historial' => fn ($q) => $q->whereIn('estado_nuevo', ['Cerrada', 'Finalizada', 'EnJustificacion'])->with('actor'),
            ])
            ->latest('dia_operativo')
            ->orderByDesc('papeletas.id')
            ->paginate(15);

        return view('livewire.papeletas.rrhh-abandonos', ['abandonos' => $abandonos, 'causas' => self::CAUSAS]);
    }
}
