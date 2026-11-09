<?php

namespace App\Services;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Collection;


class EquipoDelJefeService
{
    /**
      * @return array{0: Collection<int, User>, 1: bool, 2: list<int>} [personas, esJefeDeArea, idsSoloLectura]
      */
    public function para(User $user): array
    {
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);
        $esJefeDeArea = $unidadIds->isNotEmpty();

        $paresSoloLectura = collect();

        $directos = $user->subordinadosInmediatos()->get();

        
        
        
        
        if (! $esJefeDeArea && ! $user->hasRole('admin')) {
            $idsDeJefes = $this->idsDeJefes();

            $directos = $directos->reject(
                fn (User $t) => $t->id !== $user->id && $idsDeJefes->contains($t->id)
            );
        }

        
        
        
        if (! $user->hasRole('admin')) {
            $paresSoloLectura = $this->jefesDeSusUnidades($user)
                ->reject(fn (User $j) => $j->id === $user->id);
        }

        $jefesDeSubunidades = $esJefeDeArea
            ? User::whereIn('id', UnidadOrganica::whereIn('id', $unidadIds)
                ->whereNotNull('jefe_id')
                ->pluck('jefe_id')
                ->unique())
                ->get()
            : collect();

        $trabajadores = $directos
            ->merge($jefesDeSubunidades)
            ->merge($paresSoloLectura)
            ->push($user)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [
            $trabajadores,
            $esJefeDeArea,
            array_values($paresSoloLectura->pluck('id')->map(fn ($id) => (int) $id)->all()),
        ];
    }

    /**
      * @return Collection<int, User>
      */
    private function jefesDeSusUnidades(User $user): Collection
    {
        $unidadIds = UnidadOrganica::where('jefe_id', $user->id)->pluck('id');

        if ($unidadIds->isEmpty()) {
            return collect();
        }

        $jefeIds = UnidadOrganica::whereIn('id', $unidadIds)
            ->whereNotNull('jefe_id')
            ->pluck('jefe_id')
            ->unique();

        return User::whereIn('id', $jefeIds)->where('activo', true)->get();
    }

    /**
      * @return Collection<int, int>
      */
    public function idsDeJefes(): Collection
    {
        return UnidadOrganica::whereNotNull('jefe_id')->pluck('jefe_id')
            ->merge(User::whereNotNull('jefe_inmediato_id')->distinct()->pluck('jefe_inmediato_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
      * @return Collection<int, int>
      */
    private function subtreeIdsDeAreasQueEncabeza(User $user): Collection
    {
        $ids = collect();

        foreach ($user->unidadesDeArea as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
