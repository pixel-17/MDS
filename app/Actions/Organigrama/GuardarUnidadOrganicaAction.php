<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuardarUnidadOrganicaAction
{

    public function ejecutar(User $actor, ?UnidadOrganica $unidad, array $datos, bool $ubicarJefe = false): UnidadOrganica
    {
        if (! $actor->hasRole('admin')) {
            $this->exigirAlcanceDeJefeDeArea($actor, $unidad, $datos);
        }

        $jefaturas = app(JefaturaUnidadService::class);
        $jefaturas->validar(
            $unidad,
            isset($datos['jefe_id']) ? (int) $datos['jefe_id'] : null,
            isset($datos['parent_id']) ? (int) $datos['parent_id'] : null,
            $ubicarJefe,
        );

        return DB::transaction(function () use ($unidad, $datos, $ubicarJefe, $jefaturas) {
            $this->validarSinCiclos($unidad, $datos['parent_id'] ?? null);

            $atributos = [
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'parent_id' => $datos['parent_id'],
                'jefe_id' => $datos['jefe_id'],
                'activo' => $datos['activo'],
            ];

            $unidad = $unidad
                ? tap($unidad)->update($atributos)
                : UnidadOrganica::create($atributos);

            if ($ubicarJefe) {
                $jefaturas->ubicarJefe($unidad->fresh());
            }

            return $unidad;
        });
    }

    private function exigirAlcanceDeJefeDeArea(User $actor, ?UnidadOrganica $unidad, array $datos): void
    {
        if ($unidad === null) {
            $this->exigirAlcanceDeJefeDeAreaAlCrear($actor, $datos);

            return;
        }

        $this->exigirAlcanceDeJefeDeAreaAlEditar($actor, $unidad, $datos);
    }

    private function exigirAlcanceDeJefeDeAreaAlCrear(User $actor, array $datos): void
    {
        $reglas = app(ReglasOrganigrama::class);

        $parentId = isset($datos['parent_id']) ? (int) $datos['parent_id'] : null;

        if (! $actor->esJefeDeArea() || $parentId === null || ! $reglas->esJefeDeAreaDe($actor, $parentId)) {
            throw new UsuarioException('Solo puedes crear sub-unidades dentro de tu propia área.');
        }

        $jefeId = isset($datos['jefe_id']) ? (int) $datos['jefe_id'] : null;

        if ($jefeId !== null) {
            $padre = UnidadOrganica::find($parentId);
            $unidadDelJefeNuevo = User::find($jefeId)?->unidadOrganica;

            if ($padre === null || $unidadDelJefeNuevo === null
                || $reglas->areaDe($actor, $unidadDelJefeNuevo) !== $reglas->areaDe($actor, $padre)) {
                throw new UsuarioException('El jefe que asignes debe pertenecer a tu área.');
            }
        }
    }

    private function exigirAlcanceDeJefeDeAreaAlEditar(User $actor, UnidadOrganica $unidad, array $datos): void
    {
        $reglas = app(ReglasOrganigrama::class);

        if (! $actor->esJefeDeArea() || ! $reglas->puedeGestionarJefaturasEn($actor, $unidad)) {
            throw new UsuarioException('Solo un administrador puede editar esta unidad orgánica.');
        }

        $soloJefatura = $datos['nombre'] === $unidad->nombre
            && ($datos['tipo'] ?? null) === $unidad->tipo
            && (int) ($datos['parent_id'] ?? 0) === (int) $unidad->parent_id
            && (bool) $datos['activo'] === (bool) $unidad->activo;

        if (! $soloJefatura) {
            throw new UsuarioException('Como jefe de área solo puedes cambiar quién jefatura la unidad.');
        }

        $jefeId = isset($datos['jefe_id']) ? (int) $datos['jefe_id'] : null;

        if ($jefeId !== null && $jefeId !== (int) $unidad->jefe_id) {
            $unidadNuevo = User::find($jefeId)?->unidadOrganica;

            if ($unidadNuevo === null || $reglas->areaDe($actor, $unidadNuevo) !== $reglas->areaDe($actor, $unidad)) {
                throw new UsuarioException('El nuevo jefe debe pertenecer a tu área.');
            }
        }
    }

    private function validarSinCiclos(?UnidadOrganica $unidad, mixed $parentId): void
    {
        if (! $unidad || ! $parentId) {
            return;
        }

        UnidadOrganica::query()->select('id', 'parent_id')->lockForUpdate()->get();

        $prohibidos = [$unidad->id, ...$unidad->descendantIds()];

        if (in_array((int) $parentId, $prohibidos, true)) {
            throw new UsuarioException('Esa unidad no puede ser su propio padre ni el de un descendiente suyo.');
        }
    }
}
