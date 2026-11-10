<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama\Concerns;

use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Exceptions\UsuarioException;
use App\Models\MovimientoOrganigrama;
use App\Models\Papeleta;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\Organigrama\ArmadorOrganigrama;
use Illuminate\Support\Collection;

trait MueveTrabajador
{
    /**
     * @var array{trabajador: int, origen: int, destino: int, cambiar_sede: bool}|null
     */
    public ?array $propuesta = null;

    public function proponerMovimiento(int $trabajadorId, int $destinoId): void
    {
        $usuario = $this->autorizar();
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;

        if (! $this->modoEdicion) {
            return;
        }

        $trabajador = User::find($trabajadorId);
        $destino = UnidadOrganica::find($destinoId);

        if (! $trabajador || ! $destino) {
            $this->mensajeError = 'No se encontró a la persona o la unidad.';

            return;
        }

        if ($trabajador->unidadesQueEncabeza()->exists()) {
            $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

            return;
        }

        if ((int) $trabajador->unidad_organica_id === (int) $destino->id) {
            $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

            return;
        }

        try {
            $vista = app(MoverTrabajadorAction::class)->previsualizar($usuario, $trabajador, $destino);
        } catch (UsuarioException $e) {
            $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

            if ($this->propuestaJefe !== null) {
                $this->mensajeError = null;

                return;
            }

            $this->mensajeError = $e->getMessage();

            return;
        }

        $this->propuesta = [
            'trabajador' => (int) $trabajador->id,
            'origen' => (int) $trabajador->unidad_organica_id,
            'destino' => (int) $destino->id,
            'cambiar_sede' => $vista['sede']['propone_cambio'],
        ];
    }

    public function ascenderTrabajador(): void
    {
        $usuario = $this->autorizar();
        $propuesta = $this->propuesta;

        if (! $this->modoEdicion || ! is_array($propuesta)) {
            return;
        }

        $trabajador = User::find((int) $propuesta['trabajador']);
        $destino = UnidadOrganica::find((int) $propuesta['destino']);

        if (! $trabajador || ! $destino) {
            return;
        }

        $this->mensajeOk = $this->mensajeError = null;
        $this->proponerMovimientoJefe($usuario, $trabajador, $destino);

        if ($this->propuestaJefe !== null) {
            $this->propuesta = null;
        }
    }

    public function confirmarMovimiento(): void
    {
        $usuario = $this->autorizar();
        $propuesta = $this->propuesta;
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;

        if (! $this->modoEdicion || ! is_array($propuesta)) {
            return;
        }

        try {
            $movimiento = app(MoverTrabajadorAction::class)->ejecutar(
                $usuario,
                (int) $propuesta['trabajador'],
                (int) $propuesta['destino'],
                (int) $propuesta['origen'],
                true,
                (bool) $propuesta['cambiar_sede'],
            );
        } catch (UsuarioException $e) {
            $this->mensajeError = $e->getMessage();

            return;
        }

        $movimiento->load(['trabajador', 'unidadAnterior', 'unidadNueva']);
        $this->mensajeOk = $movimiento->trabajador?->nombre_completo.' pasó de '
            .($movimiento->unidadAnterior->nombre ?? '—').' a '.($movimiento->unidadNueva->nombre ?? '—').'.';
    }

    public function deshacerMovimiento(int $movimientoId): void
    {
        $usuario = $this->autorizar();
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;

        if (! $this->modoEdicion) {
            return;
        }

        $movimiento = MovimientoOrganigrama::find($movimientoId);

        if (! $movimiento) {
            $this->mensajeError = 'El movimiento ya no existe.';

            return;
        }

        try {
            $reversion = app(MoverTrabajadorAction::class)->deshacer($usuario, $movimiento);
        } catch (UsuarioException $e) {
            $this->mensajeError = $e->getMessage();

            return;
        }

        $reversion->load(['trabajador', 'unidadNueva']);
        $this->mensajeOk = 'Se deshizo el movimiento: '.$reversion->trabajador?->nombre_completo
            .' volvió a '.($reversion->unidadNueva->nombre ?? '—').'.';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vistaPreviaMovimiento(User $usuario): ?array
    {
        if ($this->propuesta === null) {
            return null;
        }

        $trabajador = User::find((int) $this->propuesta['trabajador']);
        $destino = UnidadOrganica::find((int) $this->propuesta['destino']);

        if (! $trabajador || ! $destino) {
            return null;
        }

        try {
            $vista = app(MoverTrabajadorAction::class)->previsualizar($usuario, $trabajador, $destino);
        } catch (UsuarioException) {
            return null;
        }

        $vista['pendientes'] = Papeleta::query()
            ->where('trabajador_id', $trabajador->id)
            ->whereIn('estado', array_keys(ArmadorOrganigrama::ESTADOS_PENDIENTES))
            ->count();

        return $vista;
    }

    /**
     * @param  array<string, mixed>|null  $ficha
     * @param  Collection<int, UnidadOrganica>  $unidades
     * @param  array<int, true>  $enAlcance
     * @return list<array{id: int, label: string, hint: string}>
     */
    private function destinosParaMover(?array $ficha, Collection $unidades, array $enAlcance): array
    {
        if (! $this->modoEdicion || $ficha === null) {
            return [];
        }

        if (! $ficha['persona'] instanceof User) {
            return [];
        }

        $actual = (int) $ficha['persona']->unidad_organica_id;

        $destinos = $unidades
            ->filter(fn (UnidadOrganica $u) => isset($enAlcance[$u->id]) && $u->activo && (int) $u->id !== $actual)
            ->map(fn (UnidadOrganica $u) => [
                'id' => (int) $u->id,
                'label' => $u->nombre,
                'hint' => $u->jefe->nombre_completo ?? 'sin jefe',
            ])
            ->all();

        return array_values($destinos);
    }
}
