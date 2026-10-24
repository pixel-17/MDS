<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class UnidadOrganica extends Model
{

    protected $attributes = [
        'activo' => true,
    ];

    protected $fillable = [
        'nombre',
        'tipo',
        'parent_id',
        'jefe_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class, 'parent_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'parent_id');
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }

    public function esJefeDeLaUnidad(User $usuario): bool
    {
        if ($usuario->id === null) {
            return false;
        }

        return $this->jefe_id !== null && (int) $this->jefe_id === (int) $usuario->id;
    }

    public function miembros(): HasMany
    {
        return $this->hasMany(User::class, 'unidad_organica_id');
    }

    public function jefeInmediato(): ?User
    {
        return $this->jefe;
    }

    public function jefeArea(): ?User
    {
        return $this->padre?->jefe;
    }

    public function jefaturasDe(User $usuario): array
    {
        $padre = $this->padre;

        if ($this->esJefeDeLaUnidad($usuario)) {
            $jefeInmediatoId = $padre?->jefe_id !== null ? (int) $padre->jefe_id : null;
            $jefeAreaId = $padre?->padre?->jefe_id !== null ? (int) $padre->padre->jefe_id : null;

            return [$jefeInmediatoId, $jefeAreaId ?? $jefeInmediatoId];
        }

        $jefeInmediatoId = $this->jefe_id !== null ? (int) $this->jefe_id : null;
        $jefeAreaId = $padre?->jefe_id !== null ? (int) $padre->jefe_id : null;

        return [$jefeInmediatoId, $jefeAreaId ?? $jefeInmediatoId];
    }

    public static function arbolEnMemoria(): Collection
    {
        $porId = static::query()->with(['jefe'])->get()->keyBy('id');

        foreach ($porId as $unidad) {
            $unidad->setRelation('padre', $unidad->parent_id !== null ? $porId->get($unidad->parent_id) : null);
        }

        return $porId;
    }

    public function descendantIds(): array
    {
        $porPadre = static::query()
            ->select('id', 'parent_id')
            ->get()
            ->groupBy('parent_id');

        $ids = [];

        $visitados = [$this->id => true];
        $pendientes = [$this->id];

        while ($pendientes) {
            $idActual = array_pop($pendientes);

            foreach ($porPadre->get($idActual, []) as $hijo) {
                if (isset($visitados[$hijo->id])) {
                    continue;
                }

                $visitados[$hijo->id] = true;
                $ids[] = $hijo->id;
                $pendientes[] = $hijo->id;
            }
        }

        return $ids;
    }
}
