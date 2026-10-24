<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.trabajador-layout')]
#[Title('Mis papeletas')]
class TrabajadorIndex extends Component
{
    use EscuchaNotificacionesEnVivo, WithPagination;

    public string $buscar = '';

    #[Url(as: 'todas')]
    public bool $verTodas = false;

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedVerTodas(): void
    {

        if (! $this->verTodas) {
            $this->buscar = '';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        $termino = trim($this->buscar);
        $usuario = Auth::user();

        $papeletas = $usuario->papeletas()
            ->with(['motivo', 'sede', 'retorno'])
            ->when(! $this->verTodas, fn ($q) => $q->delTurnoVigente())
            ->when($termino !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('motivo', fn ($m) => $m->where('nombre', 'like', "%{$termino}%"))
                ->orWhere('justificacion', 'like', "%{$termino}%")))
            ->latest()
            ->orderByDesc('papeletas.id')
            ->paginate(15);

        $tieneAlguna = ! $papeletas->isEmpty() || $this->verTodas || $termino !== ''
            || $usuario->papeletas()->exists();

        return view('livewire.papeletas.trabajador-index', [
            'papeletas' => $papeletas,
            'tieneAlguna' => $tieneAlguna,
        ]);
    }
}
