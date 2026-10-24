<?php

namespace App\Livewire\Configuraciones;

use App\Models\Configuracion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Configuraciones')]
class ConfiguracionIndex extends Component
{
    public function render(): View
    {
        return view('livewire.configuraciones.configuracion-index', [

            'configuraciones' => Configuracion::orderBy('clave')->get()
                ->reject(fn (Configuracion $c) => ConfiguracionForm::esClaveDeTurno($c->clave))
                ->values(),
        ]);
    }
}
