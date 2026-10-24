<?php

namespace App\Services;

use App\Models\Sustento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReporteSustentosService
{

    public function query(User $usuario, array $filtros = []): Builder
    {
        $query = Sustento::query()
            ->with(['papeleta.trabajador.sede', 'papeleta.motivo', 'revisadoPor']);

        if (! $usuario->hasRole('admin') && ! $usuario->hasRole('rrhh')) {
            $query->whereHas('papeleta', fn (Builder $q) => $q->deEquipoDe($usuario));
        }

        if (! empty($filtros['trabajador_id'])) {
            $query->whereHas('papeleta', fn (Builder $q) => $q->where('trabajador_id', $filtros['trabajador_id']));
        }

        if (! empty($filtros['buscar'])) {
            $buscar = trim($filtros['buscar']);

            $query->whereHas('papeleta.trabajador', fn (Builder $q) => $q->where(function (Builder $q2) use ($buscar) {
                $q2->where('name', 'like', "%{$buscar}%")
                    ->orWhere('apellido', 'like', "%{$buscar}%")
                    ->orWhere('dni', 'like', "%{$buscar}%");
            }));
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('created_at', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['hasta']);
        }

        return $query->orderByDesc('created_at');
    }
}
