<?php

namespace App\Livewire\Catalogos;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Catálogos')]
class CatalogoIndex extends Component
{
    public function render(): View
    {
        return view('livewire.catalogos.catalogo-index', [
            'catalogos' => [
                ['nombre' => 'Sedes', 'descripcion' => 'Locales con geocerca para marcar el retorno.', 'icono' => 'map-pin', 'ruta' => route('sedes.index')],
                ['nombre' => 'Unidades orgánicas', 'descripcion' => 'Áreas, sus jefes y la jerarquía entre ellas.', 'icono' => 'building', 'ruta' => route('unidades-organicas.index')],
                ['nombre' => 'Motivos', 'descripcion' => 'Motivos de papeleta y sus reglas de sustento.', 'icono' => 'tag', 'ruta' => route('motivos.index')],
                ['nombre' => 'Turnos', 'descripcion' => 'Hora de inicio y fin de cada turno (Mañana, Tarde, Noche y Día).', 'icono' => 'clock', 'ruta' => route('turnos.index')],
                ['nombre' => 'Usuarios', 'descripcion' => 'Cuentas, roles y activación/desactivación.', 'icono' => 'user-circle', 'ruta' => route('usuarios-admin.index')],
                ['nombre' => 'Configuraciones', 'descripcion' => 'Parámetros globales del sistema.', 'icono' => 'cog', 'ruta' => route('configuraciones.index')],
            ],
        ]);
    }
}
