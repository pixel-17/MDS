<?php

namespace App\Livewire\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

trait PaginaListasDeBandeja
{

    protected int $porPaginaBandeja = 10;

    abstract protected function nombresDePaginadores(): array;

    public function updatedBuscar(): void
    {
        foreach ($this->nombresDePaginadores() as $nombre) {
            $this->resetPage($nombre);
        }
    }

    protected function paginarLista(Builder $consulta, string $nombre): LengthAwarePaginator
    {
        $lista = (clone $consulta)->paginate($this->porPaginaBandeja, ['*'], $nombre);

        if ($lista->currentPage() > $lista->lastPage()) {
            $ultima = $lista->lastPage();

            $this->setPage($ultima, $nombre);

            $lista = $consulta->paginate($this->porPaginaBandeja, ['*'], $nombre, $ultima);
        }

        return $lista;
    }
}
