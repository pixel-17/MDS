<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
  * @property int $id
  * @property string $nombre
  * @property string|null $tipo
  * @property int|null $parent_id
  * @property int|null $jefe_id
  * @property bool $activo
  * @property-read UnidadOrganica|null $padre
  * @property-read User|null $jefe
  * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $miembros
  */
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

    /**
      * @return BelongsTo<UnidadOrganica, $this>
      */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class, 'parent_id');
    }

    /**
      * @return HasMany<UnidadOrganica, $this>
      */
    public function hijos(): HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'parent_id');
    }

    /**
      * @return BelongsTo<User, $this>
      */
    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }

    
    public function esJefeDeLaUnidad(User $usuario): bool
    {
        return $this->jefe_id !== null && (int) $this->jefe_id === (int) $usuario->id;
    }

    
    /**
      * @return HasMany<User, $this>
      */
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

    /**
      * @return array{0: ?int, 1: ?int}
      */
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

    /**
      * @return Collection<int, UnidadOrganica>
      */
    public static function arbolEnMemoria(): Collection
    {
        /** @var Collection<int, UnidadOrganica> $porId */
        $porId = static::query()->with(['jefe'])->get()->keyBy('id');

        foreach ($porId as $unidad) {
            $unidad->setRelation('padre', $unidad->parent_id !== null ? $porId->get($unidad->parent_id) : null);
        }

        return $porId;
    }

    /**
      * @return array<int>
      */
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
