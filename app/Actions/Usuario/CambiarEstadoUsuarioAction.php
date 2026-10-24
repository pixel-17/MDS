<?php

namespace App\Actions\Usuario;

use App\Actions\Organigrama\GuardarUnidadOrganicaAction;
use App\Actions\Organigrama\PapeletasAbiertasService;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CambiarEstadoUsuarioAction
{
    public const REEMPLAZO_EXISTENTE = 'existente';

    public const REEMPLAZO_NUEVO = 'nuevo';

    public const REEMPLAZO_VACANTE = 'vacante';

    public function motivoParaNoDesactivar(User $actor, User $usuario): ?string
    {
        if ($usuario->is($actor)) {
            return 'No puedes desactivar tu propia cuenta.';
        }

        if ($usuario->esUnicoRrhhActivo()) {
            return 'No puedes desactivar al único usuario de RR. HH. activo: sin él, toda papeleta aprobada por el jefe se autorizaría sola. Designa o reactiva primero a otra persona con rol RR. HH.';
        }

        return null;
    }

    public function jefaturasPorResolver(User $usuario): Collection
    {
        $pendientes = app(PapeletasAbiertasService::class)->contarPendientesDeJefe($usuario);

        return $usuario->unidadesQueEncabeza()->get()
            ->map(fn (UnidadOrganica $unidad) => [
                'unidad' => $unidad,
                'es_area' => $unidad->hijos()->where('activo', true)->exists(),
                'personas' => $unidad->miembros()->where('activo', true)->whereKeyNot($usuario->getKey())->count(),
                'subunidades' => $unidad->hijos()->where('activo', true)->count(),
                'pendientes' => $pendientes,
            ])
            ->filter(fn (array $fila) => $fila['personas'] > 0 || $fila['subunidades'] > 0)
            ->values();
    }

    public function desactivar(User $actor, User $usuario, array $reemplazos = []): User
    {
        $this->exigirAdmin($actor);

        if (! $usuario->activo) {
            return $usuario;
        }

        $motivo = $this->motivoParaNoDesactivar($actor, $usuario);

        if ($motivo !== null) {
            throw new UsuarioException($motivo);
        }

        $porResolver = $this->jefaturasPorResolver($usuario);

        foreach ($porResolver as $fila) {
            $decision = $reemplazos[$fila['unidad']->id] ?? null;
            $modo = $decision['modo'] ?? null;

            if (! in_array($modo, [self::REEMPLAZO_EXISTENTE, self::REEMPLAZO_NUEVO, self::REEMPLAZO_VACANTE], true)) {
                throw new UsuarioException(
                    'Decide qué pasa con la jefatura de «'.$fila['unidad']->nombre.'»: elegir a otra persona, crear un jefe nuevo o dejarla sin jefe.'
                );
            }
        }

        DB::transaction(function () use ($actor, $usuario, $reemplazos) {
            foreach ($usuario->unidadesQueEncabeza()->get() as $unidad) {
                $this->relevarJefatura($actor, $unidad, $usuario, $reemplazos[$unidad->id] ?? ['modo' => self::REEMPLAZO_VACANTE]);
            }

            $usuario->forceFill(['activo' => false])->save();
            $usuario->tokens()->delete();
            app(GeneradorTurnoMensualService::class)->retirarTurno($usuario);
        });

        return $usuario;
    }

    private function relevarJefatura(User $actor, UnidadOrganica $unidad, User $saliente, array $decision): void
    {
        $modo = $decision['modo'] ?? self::REEMPLAZO_VACANTE;

        $guardar = fn (?int $jefeId, bool $ubicar) => app(GuardarUnidadOrganicaAction::class)->ejecutar(
            $actor,
            $unidad->fresh(),
            [
                'nombre' => $unidad->nombre,
                'tipo' => $unidad->tipo,
                'parent_id' => $unidad->parent_id,
                'jefe_id' => $jefeId,
                'activo' => $unidad->activo,
            ],
            ubicarJefe: $ubicar,
        );

        if ($modo === self::REEMPLAZO_EXISTENTE) {
            $reemplazoId = (int) ($decision['usuario_id'] ?? 0);

            if ($reemplazoId === (int) $saliente->id) {
                throw new UsuarioException('El reemplazo de «'.$unidad->nombre.'» no puede ser la misma persona.');
            }

            $candidato = User::find($reemplazoId)
                ?? throw new UsuarioException('Elige a quién reemplaza en «'.$unidad->nombre.'».');

            $guardar($reemplazoId, (int) $candidato->unidad_organica_id !== (int) $unidad->id);

            return;
        }

        $guardar(null, false);

        if ($modo === self::REEMPLAZO_NUEVO) {
            $this->crearJefeNuevo($actor, $unidad, $saliente, (array) ($decision['nuevo'] ?? []));
        }
    }

    private function crearJefeNuevo(User $actor, UnidadOrganica $unidad, User $saliente, array $nuevo): void
    {
        foreach (['name', 'apellido', 'dni', 'email', 'regimen'] as $campo) {
            if (empty($nuevo[$campo])) {
                throw new UsuarioException('Completa los datos del jefe nuevo de «'.$unidad->nombre.'» (nombre, apellido, DNI, correo y régimen).');
            }
        }

        if (! in_array($nuevo['regimen'], ['276', '728'], true)) {
            throw new UsuarioException('El régimen del jefe nuevo debe ser 276 o 728.');
        }

        app(CrearUsuarioAction::class)->ejecutar($actor, [
            'name' => $nuevo['name'],
            'apellido' => $nuevo['apellido'],
            'dni' => $nuevo['dni'],
            'email' => $nuevo['email'],
            'regimen' => $nuevo['regimen'],
            'sede_id' => $nuevo['sede_id'] ?? $saliente->sede_id,
            'unidad_organica_id' => $unidad->id,
            'tipo' => 'jefe_inmediato',
        ], true);
    }

    /** @throws UsuarioException */
    public function reactivar(User $actor, User $usuario): User
    {
        $this->exigirAdmin($actor);

        if (! $usuario->activo) {
            $usuario->forceFill(['activo' => true])->save();
        }

        return $usuario;
    }

    private function exigirAdmin(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw new UsuarioException('Solo un administrador puede activar o desactivar usuarios.');
        }
    }
}
