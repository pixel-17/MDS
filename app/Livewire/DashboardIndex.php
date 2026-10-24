<?php

namespace App\Livewire;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Inicio')]
class DashboardIndex extends Component
{
    use EscuchaNotificacionesEnVivo;

    public function mount(DashboardMetricsService $metricas): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole('admin') && ! $user->hasRole('rrhh') && ! $user->can('crearTrabajadorPropio', User::class)) {
            $this->redirectRoute('trabajador.papeletas.index');
        }
    }

    public function render(DashboardMetricsService $metricas): View
    {
        /** @var User $user */
        $user = Auth::user();

        $esAdmin = $user->hasRole('admin');
        $esRrhh = $user->hasRole('rrhh');
        $esJefe = $user->can('crearTrabajadorPropio', User::class);

        return view('livewire.dashboard', [
            'nombre' => $user->name,
            'esAdmin' => $esAdmin,
            'esRrhh' => $esRrhh,
            'esJefe' => $esJefe,

            'esJefeInmediatoPuro' => $esJefe && ! $user->esJefeDeArea(),
            'admin' => $esAdmin ? $metricas->paraAdmin() : null,
            'rrhh' => $esRrhh ? $metricas->paraRrhh($user) : null,
            'jefe' => $esJefe ? $metricas->paraJefe($user) : null,
        ]);
    }
}
