<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama;

use App\Livewire\Organigrama\Concerns\MuestraFicha;
use App\Livewire\Organigrama\Concerns\MueveJefe;
use App\Livewire\Organigrama\Concerns\MueveTrabajador;
use App\Models\Sede;
use App\Models\User;
use App\Services\Organigrama\ArmadorOrganigrama;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Organigrama del área')]
class OrganigramaArbol extends Component
{
    use MuestraFicha;
    use MueveJefe;
    use MueveTrabajador;

    public string $buscar = '';

    public bool $verInactivos = false;

    public string $sede = '';

    public bool $modoEdicion = false;

    public ?string $mensajeOk = null;

    public ?string $mensajeError = null;

    public function mount(): void
    {
        $this->autorizarLectura();

        $personaId = request()->integer('persona') ?: null;

        if ($personaId) {
            $this->personaId = $personaId;
        }
    }

    public function updatedSede(): void
    {

        if ($this->sede !== '' && $this->sede !== 'sin' && ! ctype_digit($this->sede)) {
            $this->sede = '';
        }
    }

    public function limpiarSede(): void
    {
        $this->sede = '';
    }

    public function alternarEdicion(): void
    {
        $this->autorizar();

        $this->modoEdicion = ! $this->modoEdicion;
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;
    }

    public function cancelarMovimiento(): void
    {
        $this->propuesta = null;
        $this->propuestaJefe = null;
    }

    #[On('org-actualizado')]
    public function alActualizar(?string $mensaje = null): void
    {
        $this->autorizar();
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeError = null;

        if ($mensaje !== null) {
            $this->mensajeOk = $mensaje;
        }
    }

    private function autorizar(): User
    {

        $usuario = auth()->user();

        abort_unless(
            $usuario && ($usuario->hasRole('admin') || $usuario->esJefeDeEquipo()),
            403,
        );

        return $usuario;
    }

    private function autorizarLectura(): User
    {

        $usuario = auth()->user();

        abort_unless(
            $usuario && ($usuario->hasRole('admin') || $usuario->esJefeDeEquipo()),
            403,
        );

        return $usuario;
    }

    public function render(): View
    {
        $usuario = $this->autorizarLectura();
        $puedeEditar = $usuario->hasRole('admin') || $usuario->esJefeDeEquipo();

        if (! $puedeEditar) {
            $this->modoEdicion = false;
        }

        $armador = new ArmadorOrganigrama($this->buscar, $this->sede, $this->verInactivos);
        ['raices' => $raices, 'stats' => $stats, 'conteoSedes' => $conteoSedes, 'unidades' => $unidades, 'enAlcance' => $enAlcance] = $armador->armar($usuario);

        $ficha = $armador->fichaDe($this->personaId, $unidades, $enAlcance);

        return view('livewire.organigrama.organigrama-arbol', [
            'raices' => $raices,
            'stats' => $stats,
            'esAdmin' => $usuario->hasRole('admin'),

            'esJefeDeArea' => $usuario->esJefeDeArea(),
            'puedeEditar' => $puedeEditar,
            'sedes' => Sede::orderBy('nombre')->get(['id', 'nombre']),
            'conteoSedes' => $conteoSedes,
            'ficha' => $ficha,
            'destinosMover' => $this->destinosParaMover($ficha, $unidades, $enAlcance),
            'movimiento' => $this->vistaPreviaMovimiento($usuario),
            'movimientoJefe' => $this->vistaPreviaMovimientoJefe($usuario),
            'historial' => $armador->historialDe($usuario, $enAlcance, $this->modoEdicion),
        ]);
    }
}
