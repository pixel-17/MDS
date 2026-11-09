<?php

namespace App\Http\Controllers\Usuario;

use App\Http\Controllers\Controller;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;


class UsuarioController extends Controller
{
    public function index(): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        
        if ($user->hasRole('admin')) {
            return redirect()->route('usuarios-admin.index');
        }
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);

        $usuarios = $unidadIds->isNotEmpty()
            ? User::whereIn('unidad_organica_id', $unidadIds)->orderBy('name')->get()
            : $user->trabajadoresComoJefeInmediato();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'esJefeDeArea' => $unidadIds->isNotEmpty(),
            'puedeCrear' => $user->can('puedeCrearAlgo', User::class),
            'avisosJefatura' => $this->avisosDeUnidadesSinJefe($unidadIds),
        ]);
    }

    /**
      * @param  Collection<int, int>  $unidadIds
      * @return list<string>
      */
    private function avisosDeUnidadesSinJefe(Collection $unidadIds): array
    {
        if ($unidadIds->isEmpty()) {
            return [];
        }

        $avisos = UnidadOrganica::with('jefe')
            ->whereIn('id', $unidadIds)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->filter(fn (UnidadOrganica $unidad) => ! $unidad->jefe?->activo)
            ->map(fn (UnidadOrganica $unidad) => "{$unidad->nombre}: sin jefe activo.")
            ->all();

        return array_values($avisos);
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
