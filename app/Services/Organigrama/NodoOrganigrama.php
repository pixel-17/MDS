<?php

declare(strict_types=1);

namespace App\Services\Organigrama;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use LogicException;
use OutOfBoundsException;

/**
  * @implements \ArrayAccess<string, mixed>
  */
final class NodoOrganigrama implements \ArrayAccess
{
    private const CLAVES = [
        'unidad', 'jefes', 'jefes_en_filtro', 'filtro_sede',
        'miembros', 'hijos', 'total', 'sin_jefe',
    ];

    /**
      * @param  Collection<int, User>  $jefes
      * @param  array<int, int>  $jefesEnFiltro
      * @param  EloquentCollection<int, User>  $miembros
      * @param  Collection<int, NodoOrganigrama>  $hijos
      */
    public function __construct(
        public readonly UnidadOrganica $unidad,
        public readonly Collection $jefes,
        public readonly array $jefesEnFiltro,
        public readonly bool $filtroSede,
        public readonly EloquentCollection $miembros,
        public readonly Collection $hijos,
        public readonly int|float $total,
        public readonly bool $sinJefe,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, self::CLAVES, true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'unidad' => $this->unidad,
            'jefes' => $this->jefes,
            'jefes_en_filtro' => $this->jefesEnFiltro,
            'filtro_sede' => $this->filtroSede,
            'miembros' => $this->miembros,
            'hijos' => $this->hijos,
            'total' => $this->total,
            'sin_jefe' => $this->sinJefe,
            default => throw new OutOfBoundsException("Clave desconocida en NodoOrganigrama: {$offset}"),
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('NodoOrganigrama es de solo lectura.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('NodoOrganigrama es de solo lectura.');
    }
}