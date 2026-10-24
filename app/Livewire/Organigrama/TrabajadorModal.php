<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama;

use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Livewire\Concerns\ManejaExcepcionesDeUsuario;
use App\Livewire\Concerns\RequiereAdmin;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class TrabajadorModal extends Component
{
    use ManejaExcepcionesDeUsuario;
    use RequiereAdmin;

    public bool $abierto = false;

    #[Locked]
    public ?int $trabajadorId = null;

    public ?int $unidadId = null;

    public ?int $sedeId = null;

    public bool $activo = true;

    public ?string $error = null;

    public ?string $ok = null;

    #[On('org-trabajador-abrir')]
    public function abrir(int $id): void
    {
        $this->autorizarAdmin();

        $t = User::findOrFail($id);

        $this->trabajadorId = $t->id;
        $this->unidadId = $t->unidad_organica_id;
        $this->sedeId = $t->sede_id;
        $this->activo = (bool) $t->activo;
        $this->error = $this->ok = null;
        $this->resetValidation();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function guardar(MoverTrabajadorAction $mover, CambiarEstadoUsuarioAction $estado): void
    {
        $this->autorizarAdmin();
        $this->error = $this->ok = null;

        $this->validate([
            'unidadId' => ['nullable', 'exists:unidad_organicas,id'],
            'sedeId' => ['nullable', 'exists:sedes,id'],
            'activo' => ['boolean'],
        ]);

        $t = User::find($this->trabajadorId);

        if (! $t) {
            $this->error = 'La persona ya no existe.';

            return;
        }

        $actor = auth()->user();

        if ($t->activo && ! $this->activo) {
            $motivo = $estado->motivoParaNoDesactivar($actor, $t);

            if ($motivo !== null) {
                $this->error = $motivo;

                return;
            }

            if ($estado->jefaturasPorResolver($t)->isNotEmpty()) {
                $this->abierto = false;
                $this->dispatch('usuario-baja-abrir', id: $t->id);

                return;
            }
        }

        if (! $this->intentar(function () use ($mover, $actor, $t) {

            if ($this->unidadId !== null && (int) $this->unidadId !== (int) $t->unidad_organica_id) {
                if ($t->unidad_organica_id === null) {

                    $mover->exigirJefeInmediato(UnidadOrganica::findOrFail($this->unidadId));
                    $t->unidad_organica_id = $this->unidadId;
                    $t->save();
                } else {
                    $mover->ejecutar($actor, $t->id, $this->unidadId, (int) $t->unidad_organica_id, true, false);
                }
                $t->refresh();
            }
        }, fn ($e) => $this->error = $e->getMessage())) {
            return;
        }

        $t->forceFill(['sede_id' => $this->sedeId])->save();

        if (! $this->intentar(function () use ($estado, $actor, $t) {
            if ($t->activo && ! $this->activo) {
                $estado->desactivar($actor, $t);
            } elseif (! $t->activo && $this->activo) {
                $estado->reactivar($actor, $t);
            }
        }, fn ($e) => $this->error = $e->getMessage())) {
            return;
        }

        $this->abierto = false;
        $this->dispatch('org-actualizado', mensaje: 'Trabajador actualizado.');
    }

    public function render(): View
    {
        if (! $this->abierto) {
            return view('livewire.organigrama.trabajador-modal', [
                't' => null, 'unidades' => [], 'sedes' => [], 'destino' => null,
            ]);
        }

        $t = User::with(['jefeInmediato', 'sede'])->find($this->trabajadorId);

        $destino = $t && $this->unidadId && (int) $this->unidadId !== (int) $t->unidad_organica_id
            ? UnidadOrganica::with('jefe.sede')->find($this->unidadId)
            : null;

        return view('livewire.organigrama.trabajador-modal', [
            't' => $t,
            'destino' => $destino,
            'unidades' => UnidadOrganica::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($u) => ['id' => $u->id, 'label' => $u->nombre, 'hint' => null])->all(),
            'sedes' => Sede::orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($s) => ['id' => $s->id, 'label' => $s->nombre, 'hint' => null])->all(),
        ]);
    }
}
