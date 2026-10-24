<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\MovimientoOrganigrama;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MoverTrabajadorAction
{

    public function previsualizar(User $actor, User $trabajador, UnidadOrganica $destino): array
    {
        $origen = $this->origenDe($trabajador);
        $this->validar($actor, $trabajador, $origen, $destino);

        [$jefeInmediatoAntes, $jefeAreaAntes] = $origen->jefaturasDe($trabajador);
        [$jefeInmediatoDespues, $jefeAreaDespues] = $destino->jefaturasDe($trabajador);

        $usuarios = User::whereIn('id', array_filter([
            $jefeInmediatoAntes, $jefeAreaAntes, $jefeInmediatoDespues, $jefeAreaDespues,
        ]))->get()->keyBy('id');
        $usuarioDe = fn (?int $id): ?User => $id === null ? null : $usuarios->get($id);

        $avisos = [];

        $sedePropuestaId = $this->sedePropuestaId($trabajador, $destino);
        $sedeActual = $trabajador->sede_id ? Sede::find($trabajador->sede_id) : null;

        return [
            'trabajador' => $trabajador,
            'origen' => $origen,
            'destino' => $destino,
            'jefe_inmediato' => [
                'antes' => $usuarioDe($jefeInmediatoAntes),
                'despues' => $usuarioDe($jefeInmediatoDespues),
                'cambia' => $jefeInmediatoAntes !== $jefeInmediatoDespues,
            ],
            'jefe_area' => [
                'antes' => $usuarioDe($jefeAreaAntes),
                'despues' => $usuarioDe($jefeAreaDespues),
                'cambia' => $jefeAreaAntes !== $jefeAreaDespues,
            ],
            'sede' => [
                'actual' => $sedeActual,
                'propuesta' => $sedePropuestaId ? Sede::find($sedePropuestaId) : null,
                'propone_cambio' => $sedePropuestaId !== null,
            ],
            'avisos' => $avisos,
        ];
    }

    private function sedePropuestaId(User $trabajador, UnidadOrganica $destino): ?int
    {
        $sedeDelJefe = $destino->jefe?->sede_id;

        if ($sedeDelJefe === null || (int) $sedeDelJefe === (int) $trabajador->sede_id) {
            return null;
        }

        return (int) $sedeDelJefe;
    }

    public function ejecutar(
        User $actor,
        int $trabajadorId,
        int $destinoId,
        int $origenEsperadoId,
        bool $confirmado,
        bool $cambiarSede = false,
    ): MovimientoOrganigrama {
        if (! $confirmado) {
            throw new UsuarioException('Falta confirmar explícitamente el movimiento.');
        }

        return DB::transaction(function () use ($actor, $trabajadorId, $destinoId, $origenEsperadoId, $cambiarSede) {
            $trabajador = User::query()->lockForUpdate()->find($trabajadorId)
                ?? throw new UsuarioException('La persona ya no existe.');
            $destino = UnidadOrganica::find($destinoId)
                ?? throw new UsuarioException('La unidad de destino ya no existe.');

            if ((int) $trabajador->unidad_organica_id !== $origenEsperadoId) {
                throw new UsuarioException('La persona ya no está en la unidad desde la que la arrastraste. Actualiza la pantalla e inténtalo de nuevo.');
            }

            $this->validar($actor, $trabajador, $this->origenDe($trabajador), $destino);

            return $this->aplicar($actor, $trabajador, $destino, [
                'sede_id' => $cambiarSede ? $this->sedePropuestaId($trabajador, $destino) : null,
            ]);
        });
    }

    public function deshacer(User $actor, MovimientoOrganigrama $movimiento): MovimientoOrganigrama
    {
        return DB::transaction(function () use ($actor, $movimiento) {
            $original = MovimientoOrganigrama::query()->lockForUpdate()->find($movimiento->id)
                ?? throw new UsuarioException('El movimiento ya no existe.');
            $trabajador = $original->trabajador_id
                ? User::query()->lockForUpdate()->find($original->trabajador_id)
                : null;

            [, $destino] = $this->validarDeshacer($actor, $original, $trabajador);

            $reversion = $this->aplicar($actor, $trabajador, $destino, [
                'sede_id' => $original->cambioSede() ? $original->sede_anterior_id : null,
                'revierte' => $original,
            ]);

            $original->forceFill([
                'deshecho_at' => now(),
                'deshecho_por_id' => $actor->id,
            ])->save();

            return $reversion;
        });
    }

    public function puedeDeshacer(User $actor, MovimientoOrganigrama $movimiento): bool
    {
        try {
            $this->validarDeshacer($actor, $movimiento, $movimiento->trabajador_id ? User::find($movimiento->trabajador_id) : null);

            return true;
        } catch (UsuarioException) {
            return false;
        }
    }

    private function validarDeshacer(User $actor, MovimientoOrganigrama $movimiento, ?User $trabajador): array
    {
        if ($movimiento->deshecho_at !== null) {
            throw new UsuarioException('Este movimiento ya se deshizo.');
        }

        if ($movimiento->revierte_id !== null) {
            throw new UsuarioException('Una reversión no se puede deshacer; si hace falta, vuelve a mover a la persona.');
        }

        if (! $trabajador) {
            throw new UsuarioException('La persona ya no existe.');
        }

        $hayMasRecientes = MovimientoOrganigrama::where('trabajador_id', $movimiento->trabajador_id)
            ->where('id', '>', $movimiento->id)
            ->exists();
        if ($hayMasRecientes) {
            throw new UsuarioException('Esta persona tiene movimientos más recientes; deshaz primero el último.');
        }

        if ((int) $trabajador->unidad_organica_id !== (int) $movimiento->unidad_nueva_id) {
            throw new UsuarioException('La persona ya no está en la unidad a la que se movió.');
        }

        $actual = UnidadOrganica::find($movimiento->unidad_nueva_id)
            ?? throw new UsuarioException('La unidad a la que se movió ya no existe.');
        $anterior = UnidadOrganica::find($movimiento->unidad_anterior_id)
            ?? throw new UsuarioException('La unidad de origen ya no existe.');

        $this->validar($actor, $trabajador, $actual, $anterior);

        return [$actual, $anterior];
    }

    public function exigirJefeInmediato(UnidadOrganica $destino): void
    {
        if (! $destino->jefe?->activo) {
            throw new UsuarioException(
                'La unidad «'.$destino->nombre.'» no tiene un jefe inmediato activo: '
                .'asígnaselo en la unidad antes de moverle personas.'
            );
        }
    }

    private function origenDe(User $trabajador): UnidadOrganica
    {
        return $trabajador->unidadOrganica
            ?? throw new UsuarioException('Esa persona no pertenece a ninguna unidad orgánica.');
    }

    private function validar(User $actor, User $trabajador, UnidadOrganica $origen, UnidadOrganica $destino): void
    {
        $esAdmin = $actor->hasRole('admin');

        if (! $esAdmin && ! $actor->esJefeDeArea()) {
            throw new UsuarioException('No tienes permiso para mover personas en el organigrama.');
        }

        if ((int) $origen->id === (int) $destino->id) {
            throw new UsuarioException('La persona ya está en esa unidad.');
        }

        if (! $destino->activo) {
            throw new UsuarioException('La unidad de destino está desactivada.');
        }

        $this->exigirJefeInmediato($destino);

        if (! $esAdmin) {
            if ($trabajador->hasRole('admin')) {
                throw new UsuarioException('No puedes mover a un administrador.');
            }

            if ((int) $trabajador->id === (int) $actor->id) {
                throw new UsuarioException('No puedes moverte a ti mismo.');
            }
        }

        if ($trabajador->unidadesQueEncabeza()->exists()) {
            throw new UsuarioException('Quien encabeza una unidad no se mueve desde el organigrama: cambia primero su jefatura en la unidad.');
        }

        if (! $esAdmin) {
            $areaOrigen = app(ReglasOrganigrama::class)->areaDe($actor, $origen);

            if ($areaOrigen === null) {
                throw new UsuarioException('Esa persona está fuera de tu área.');
            }

            if ($areaOrigen !== app(ReglasOrganigrama::class)->areaDe($actor, $destino)) {
                throw new UsuarioException('Solo un administrador puede mover personas entre áreas.');
            }
        }
    }

    /**
     * @param  array{sede_id?: ?int, revierte?: ?MovimientoOrganigrama}  $opciones
     */
    private function aplicar(User $actor, User $trabajador, UnidadOrganica $destino, array $opciones = []): MovimientoOrganigrama
    {
        $antes = [
            'unidad' => $trabajador->unidad_organica_id,
            'sede' => $trabajador->sede_id,
            'jefe_inmediato' => $trabajador->jefe_inmediato_id,
            'jefe_area' => $trabajador->jefe_area_id,
        ];

        // UserObserver::saving() recalcula jefe_inmediato_id / jefe_area_id;

        $trabajador->unidad_organica_id = $destino->id;
        if (! empty($opciones['sede_id'])) {
            $trabajador->sede_id = $opciones['sede_id'];
        }
        $trabajador->save();

        $trabajador->refresh();

        return MovimientoOrganigrama::create([
            'trabajador_id' => $trabajador->id,
            'actor_id' => $actor->id,
            'unidad_anterior_id' => $antes['unidad'],
            'unidad_nueva_id' => $trabajador->unidad_organica_id,
            'sede_anterior_id' => $antes['sede'],
            'sede_nueva_id' => $trabajador->sede_id,
            'jefe_inmediato_anterior_id' => $antes['jefe_inmediato'],
            'jefe_inmediato_nuevo_id' => $trabajador->jefe_inmediato_id,
            'jefe_area_anterior_id' => $antes['jefe_area'],
            'jefe_area_nuevo_id' => $trabajador->jefe_area_id,
            'revierte_id' => ($opciones['revierte'] ?? null)?->id,
        ]);
    }
}
