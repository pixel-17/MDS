<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;

class JefaturaUnidadService
{
    /** @throws UsuarioException */
    public function validar(?UnidadOrganica $unidad, ?int $jefeId, ?int $parentId, bool $ubicarJefe = false): void
    {
        if ($parentId !== null && ($unidad === null || (int) $unidad->parent_id !== $parentId)) {
            $padre = UnidadOrganica::find($parentId)
                ?? throw new UsuarioException('La unidad de la que depende ya no existe.');

            if (! $padre->activo) {
                throw new UsuarioException('«'.$padre->nombre.'» está desactivada: no puede tener sub-unidades.');
            }
        }

        if ($jefeId === null) {
            return;
        }

        $jefe = User::find($jefeId)
            ?? throw new UsuarioException('El jefe elegido ya no existe.');

        $cambiaJefe = $unidad === null || (int) $unidad->jefe_id !== (int) $jefe->id;

        if (! $cambiaJefe) {
            return;
        }

        if (! $jefe->activo) {
            throw new UsuarioException($jefe->nombre_completo.' está desactivado: no puede encabezar una unidad.');
        }

        if ($jefe->hasRole('admin')) {
            throw new UsuarioException($jefe->nombre_completo.' es administrador: un administrador no pertenece al organigrama ni puede encabezar una unidad.');
        }

        if ($ubicarJefe) {
            $otraUnidad = $jefe->unidadesQueEncabeza()
                ->when($unidad, fn ($q) => $q->whereKeyNot($unidad->id))
                ->first();

            if ($otraUnidad) {
                throw new UsuarioException(
                    $jefe->nombre_completo.' ya encabeza «'.$otraUnidad->nombre.'»: cambia primero esa jefatura '
                    .'o desmarca «Pasarlo a esta unidad».'
                );
            }
        }
    }

    public function previsualizar(?UnidadOrganica $unidad, ?int $jefeId, ?int $parentId, bool $ubicarJefe = false): ?array
    {
        if ($unidad === null) {
            return null;
        }

        try {
            $this->validar($unidad, $jefeId, $parentId, $ubicarJefe);
            $error = null;
        } catch (UsuarioException $e) {
            $error = $e->getMessage();
        }

        $jefeAntes = $unidad->jefe_id ? User::find($unidad->jefe_id) : null;
        $jefeDespues = $jefeId ? User::find($jefeId) : null;
        $cambiaJefe = (int) $unidad->jefe_id !== (int) $jefeId;

        $padreAntes = $unidad->parent_id ? UnidadOrganica::with('jefe')->find($unidad->parent_id) : null;
        $padreDespues = $parentId ? UnidadOrganica::with('jefe')->find($parentId) : null;
        $cambiaPadre = (int) $unidad->parent_id !== (int) $parentId;

        $personas = $unidad->miembros()->where('activo', true)->count();
        $subarbol = User::whereIn('unidad_organica_id', [$unidad->id, ...$unidad->descendantIds()])
            ->where('activo', true)
            ->count();

        $fueraDeLaUnidad = $cambiaJefe
            && $jefeDespues !== null
            && (int) $jefeDespues->unidad_organica_id !== (int) $unidad->id;

        $avisos = [];

        if ($cambiaJefe && $jefeAntes) {
            $avisos[] = $jefeAntes->nombre_completo.' deja de encabezar la unidad y sigue en ella como trabajador; muévelo si hace falta.';
        }

        if ($cambiaJefe && $jefeAntes && $jefeId !== null) {
            $pendientes = app(PapeletasAbiertasService::class)->contarPendientesDeJefe($jefeAntes);

            if ($pendientes > 0) {
                $avisos[] = $pendientes.' '.($pendientes === 1 ? 'papeleta pendiente pasa' : 'papeletas pendientes pasan')
                    .' al jefe nuevo.';
            }
        }

        if ($cambiaJefe && $jefeId === null && $personas > 0) {
            $avisos[] = 'Sin jefe, no podrán crear papeletas quienes dependen directamente de esta jefatura.';
        }

        if ($fueraDeLaUnidad) {
            $suJefe = ($cambiaPadre ? $padreDespues : $padreAntes)?->jefe;
            $avisos[] = $ubicarJefe
                ? $jefeDespues->nombre_completo.' pasará a esta unidad y dependerá de '
                    .($suJefe?->nombre_completo ?? 'nadie (la unidad no tiene unidad padre con jefe)').'.'
                : $jefeDespues->nombre_completo.' seguirá en su unidad actual: su propio jefe no cambia. '
                    .'Marca «Pasarlo a esta unidad» para que dependa del jefe de la unidad padre.';
        }

        if ($cambiaPadre) {
            $avisos[] = 'La unidad se mueve con su jefe y sus personas: se recalculan los jefes de '
                .$subarbol.' '.($subarbol === 1 ? 'persona' : 'personas').' activas.';
        }

        return [
            'error' => $error,
            'jefe' => [
                'antes' => $jefeAntes,
                'despues' => $jefeDespues,
                'cambia' => $cambiaJefe,
                'fuera_de_la_unidad' => $fueraDeLaUnidad,
            ],
            'padre' => ['antes' => $padreAntes, 'despues' => $padreDespues, 'cambia' => $cambiaPadre],
            'personas' => $personas,
            'subarbol' => $subarbol,
            'avisos' => $avisos,
        ];
    }

    public function ubicarJefe(UnidadOrganica $unidad): void
    {
        $jefe = $unidad->jefe_id ? User::find($unidad->jefe_id) : null;

        if (! $jefe || (int) $jefe->unidad_organica_id === (int) $unidad->id) {
            return;
        }

        $jefe->unidad_organica_id = $unidad->id;
        $jefe->save();
    }
}
