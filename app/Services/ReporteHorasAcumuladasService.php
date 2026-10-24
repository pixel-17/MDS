<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use App\Support\Minutos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReporteHorasAcumuladasService
{
    /**
     * @param  array{trabajador_id?: int|null, buscar?: string|null, sede_id?: int|null, unidad_organica_id?: int|null, motivo_id?: int|null, regimen?: string|null, solo_con_descuento?: bool}  $filtros
     */
    public function query(User $usuario, string $mes, array $filtros = []): Builder
    {
        [$inicio, $fin] = $this->rangoDelMes($mes);

        $query = Papeleta::query()
            ->with(['trabajador.sede', 'trabajador.unidadOrganica', 'motivo', 'retorno'])
            ->whereHas('retorno')
            ->whereBetween('dia_operativo', [$inicio->toDateString(), $fin->toDateString()]);

        if (! $usuario->hasRole('admin') && ! $usuario->hasRole('rrhh')) {
            $query->deEquipoDe($usuario);
        }

        if (! empty($filtros['trabajador_id'])) {
            $query->where('trabajador_id', $filtros['trabajador_id']);
        }

        if (! empty($filtros['buscar'])) {
            $buscar = trim($filtros['buscar']);

            $query->whereHas('trabajador', fn (Builder $q) => $q->where(function (Builder $q2) use ($buscar) {
                $q2->where('name', 'like', "%{$buscar}%")
                    ->orWhere('apellido', 'like', "%{$buscar}%")
                    ->orWhere('dni', 'like', "%{$buscar}%");
            }));
        }

        if (! empty($filtros['sede_id'])) {
            $query->where('sede_id', $filtros['sede_id']);
        }

        if (! empty($filtros['unidad_organica_id'])) {
            $query->whereHas('trabajador', fn (Builder $q) => $q
                ->where('unidad_organica_id', $filtros['unidad_organica_id']));
        }

        if (! empty($filtros['motivo_id'])) {
            $query->where('motivo_id', $filtros['motivo_id']);
        }

        if (! empty($filtros['regimen'])) {
            $query->where('regimen', $filtros['regimen']);
        }

        if (! empty($filtros['solo_con_descuento'])) {
            $query->whereHas('motivo', fn (Builder $q) => $q->where('suma_descuento', true));
        }

        return $query->orderBy('dia_operativo');
    }

    public function detalle(User $usuario, string $mes, array $filtros = []): Collection
    {
        return $this->query($usuario, $mes, $filtros)->get()->map(fn (Papeleta $p) => [
            'papeleta_id' => $p->id,
            'trabajador_id' => $p->trabajador_id,
            'trabajador' => $p->trabajador->nombre_completo,
            'dni' => $p->trabajador->dni,
            'sede' => $p->trabajador->sede?->nombre,
            'unidad_organica' => $p->trabajador->unidadOrganica?->nombre,
            'dia' => $p->dia_operativo->format('Y-m-d'),
            'motivo' => $p->motivo->nombre,
            'suma_descuento' => (bool) $p->motivo->suma_descuento,
            'salida' => $p->hora_salida_real,
            'retorno' => $p->retorno->hora_servidor,
            'minutos_brutos' => $minutosBrutos = $p->hora_salida_real && $p->retorno->hora_servidor
                ? Minutos::entre($p->hora_salida_real, $p->retorno->hora_servidor)
                : 0,
            'minutos_refrigerio' => (int) $p->descuento_refrigerio_minutos,

            'minutos' => max(0, $minutosBrutos - (int) $p->descuento_refrigerio_minutos),
        ]);
    }

    public function resumenPorTrabajador(User $usuario, string $mes, array $filtros = [], ?Collection $detalle = null): Collection
    {
        return ($detalle ?? $this->detalle($usuario, $mes, $filtros))
            ->groupBy('trabajador_id')
            ->map(function (Collection $filas) {
                $conDescuento = $filas->where('suma_descuento', true);

                return [
                    'trabajador_id' => $filas->first()['trabajador_id'],
                    'trabajador' => $filas->first()['trabajador'],
                    'dni' => $filas->first()['dni'],
                    'sede' => $filas->first()['sede'],
                    'unidad_organica' => $filas->first()['unidad_organica'],
                    'papeletas' => $filas->count(),
                    'minutos_totales' => $filas->sum('minutos'),
                    'minutos_con_descuento' => $conDescuento->sum('minutos'),
                    'papeletas_con_descuento' => $conDescuento->count(),
                ];
            })
            ->values()
            ->sortByDesc('minutos_totales')
            ->values();
    }

    public function resumenDiarioPorTrabajador(User $usuario, string $mes, array $filtros = [], ?Collection $detalle = null): Collection
    {
        return ($detalle ?? $this->detalle($usuario, $mes, $filtros))
            ->groupBy(fn (array $fila) => $fila['trabajador_id'].'|'.$fila['dia'])
            ->map(fn (Collection $filas) => [
                'trabajador_id' => $filas->first()['trabajador_id'],
                'trabajador' => $filas->first()['trabajador'],
                'dia' => $filas->first()['dia'],
                'minutos' => $filas->sum('minutos'),
                'motivos' => $filas->pluck('motivo')->unique()->implode(', '),
            ])
            ->sortBy('dia')
            ->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rangoDelMes(string $mes): array
    {

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $mes = now()->format('Y-m');
        }

        $inicio = Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfMonth();

        return [$inicio->copy(), $inicio->copy()->endOfMonth()];
    }
}
