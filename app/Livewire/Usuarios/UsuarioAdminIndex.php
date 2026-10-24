<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Livewire\Concerns\ManejaExcepcionesDeUsuario;
use App\Livewire\Concerns\RequiereAdmin;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Usuarios')]
class UsuarioAdminIndex extends Component
{
    use ManejaExcepcionesDeUsuario;
    use RequiereAdmin;
    use WithPagination;

    public string $buscar = '';

    public ?string $regimen = null;

    public ?int $rolId = null;

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingRegimen(): void
    {
        $this->resetPage();
    }

    public function updatingRolId(): void
    {
        $this->resetPage();
    }

    public function desactivar(User $usuario, CambiarEstadoUsuarioAction $estado): void
    {
        $this->autorizarAdmin();

        if ($usuario->activo && $estado->motivoParaNoDesactivar(auth()->user(), $usuario) === null
            && $estado->jefaturasPorResolver($usuario)->isNotEmpty()) {
            $this->dispatch('usuario-baja-abrir', id: $usuario->id);

            return;
        }

        if ($estado->jefaturasPorResolver($usuario)->isNotEmpty()) {
            $this->dispatch('usuario-baja-abrir', id: $usuario->id);

            return;
        }

        if (! $this->intentar(
            fn () => $estado->desactivar(auth()->user(), $usuario),
            fn ($e) => session()->now('error', $e->getMessage()),
        )) {
            return;
        }

        session()->now('mensaje', 'Usuario desactivado: ya no podrá iniciar sesión.');
    }

    #[On('usuarios-actualizado')]
    public function refrescar(): void {}

    public function reactivar(User $usuario, CambiarEstadoUsuarioAction $estado): void
    {
        $this->autorizarAdmin();

        if (! $this->intentar(
            fn () => $estado->reactivar(auth()->user(), $usuario),
            fn ($e) => session()->now('error', $e->getMessage()),
        )) {
            return;
        }

        session()->now('mensaje', 'Usuario reactivado.');
    }

    public function render(): View
    {
        $usuarios = User::with(['unidadOrganica', 'roles'])
            ->when($this->buscar, fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->buscar}%")
                    ->orWhere('apellido', 'like', "%{$this->buscar}%")
                    ->orWhere('dni', 'like', "%{$this->buscar}%")
                    ->orWhere('email', 'like', "%{$this->buscar}%")))
            ->when($this->regimen, fn ($query) => $query->where('regimen', $this->regimen))
            ->when($this->rolId, fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('roles.id', $this->rolId)))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.usuarios.usuario-admin-index', [
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
