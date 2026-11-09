<?php

namespace App\Livewire\Concerns;

use App\Models\Papeleta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;


trait PaginaListasDeBandeja
{
    
    protected int $porPaginaBandeja = 10;

    /**
     * Nombres de los paginadores (uno por lista), p. ej. 'porDecidirPage'.
     *
     * @return array<int, string>
     */
    abstract protected function nombresDePaginadores(): array;

    
    public function updatedBuscar(): void
    {
        foreach ($this->nombresDePaginadores() as $nombre) {
            $this->resetPage($nombre);
        }
    }

    /**
     * Pagina una lista. Si la página pedida ya no existe (p. ej. se
     * aprobó la última papeleta de la página 2), vuelve a la última
     * página válida en vez de mostrar la lista vacía.
     *
     * @param  Builder<Papeleta>  $consulta
     * @return LengthAwarePaginator<int, Papeleta>
     */
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
