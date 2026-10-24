<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use App\Support\Minutos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HistorialTrabajadorService
{

    public function trabajadoresVisibles(User $usuario, string $buscar = ''): Collection
    {
        if ($usuario->hasRole('admin') || $usuario->hasRole('rrhh')) {
            return User::role('trabajador')
                ->when($buscar !== '', fn (Builder $q) => $q->where(function (Builder $q2) use ($buscar) {
                    $q2->where('name', 'like', "%{$buscar}%")
                        ->orWhere('apellido', 'like', "%{$buscar}%")
                        ->orWhere('dni', 'like', "%{$buscar}%");
                }))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name', 'apellido', 'dni']);
        }

        $equipo = $usuario->trabajadoresParaReportes();

        if ($buscar === '') {
            return $equipo->sortBy('name')->take(15)->values();
        }

        $buscarNormalizado = mb_strtolower($buscar);

        return $equipo
            ->filter(fn (User $t) => str_contains(mb_strtolower($t->nombre_completo.' '.$t->dni), $buscarNormalizado))
            ->sortBy('name')
            ->take(15)
            ->values();
    }

    public function puedeVer(User $usuario, User $trabajador): bool
    {
        if ($usuario->hasRole('admin') || $usuario->hasRole('rrhh')) {
            return true;
        }

        return User::equipoDe($usuario)->whereKey($trabajador->id)->exists();
    }

    public function historial(User $trabajador): Collection
    {
        return Papeleta::where('trabajador_id', $trabajador->id)
            ->with(['motivo', 'sede', 'retorno', 'sustentos'])
            ->orderByDesc('dia_operativo')
            ->get()
            ->map(fn (Papeleta $p) => [
                'id' => $p->id,
                'dia' => $p->dia_operativo,
                'motivo' => $p->motivo->nombre,
                'sede' => $p->sede?->nombre,
                'estado' => class_basename($p->estado),

                'estado_fqcn' => get_class($p->estado),
                'abandono' => $p->esAbandono(),
                'salida' => $p->hora_salida_real,
                'retorno' => $p->retorno?->hora_servidor,
                'minutos' => ($p->hora_salida_real && $p->retorno)
                    ? Minutos::entre($p->hora_salida_real, $p->retorno->hora_servidor)
                    : null,
                'suma_descuento' => (bool) $p->motivo->suma_descuento,
                'sustento_estado' => $p->sustentos->last()?->estado,
            ]);
    }

    public function resumen(Collection $historial): array
    {
        $conHoras = $historial->filter(fn (array $f) => $f['minutos'] !== null);

        return [
            'papeletas_total' => $historial->count(),
            'rechazadas' => $historial->where('estado', 'Rechazada')->count(),
            'vencidas' => $historial->where('estado', 'Vencida')->count(),
            'minutos_totales' => $conHoras->sum('minutos'),
            'minutos_con_descuento' => $conHoras->where('suma_descuento', true)->sum('minutos'),
        ];
    }
}
