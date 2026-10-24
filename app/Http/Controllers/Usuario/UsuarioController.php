<?php

namespace App\Http\Controllers\Usuario;

use App\Actions\Usuario\CrearUsuarioAction;
use App\Actions\Usuario\EditarUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\CrearUsuarioRequest;
use App\Http\Requests\Usuario\EditarUsuarioRequest;
use App\Models\Sede;
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
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return redirect()->route('usuarios-admin.index');
        }
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);

        $usuarios = $unidadIds->isNotEmpty()
            ? User::whereIn('unidad_organica_id', $unidadIds)->where('id', '!=', $user->id)->orderBy('name')->get()
            : $user->trabajadoresComoJefeInmediato();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'esJefeDeArea' => $unidadIds->isNotEmpty(),
            'puedeCrear' => $user->can('puedeCrearAlgo', User::class),
            'avisosJefatura' => $this->avisosDeUnidadesSinJefe($unidadIds),
        ]);
    }

    public function create(): View
    {
        $user = Auth::user();
        $this->authorize('puedeCrearAlgo', User::class);

        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);

        return view('usuarios.crear', [
            'esJefeDeArea' => $unidadIds->isNotEmpty(),
            'unidades' => $unidadIds->isNotEmpty()
                ? UnidadOrganica::with('jefe.sede')->whereIn('id', $unidadIds)->orderBy('nombre')->get()
                : collect(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(),

            'regimenCreador' => $user->regimen,

            'unidadPreseleccionada' => $unidadIds->contains((int) request('unidad')) ? (int) request('unidad') : null,
        ]);
    }

    public function store(CrearUsuarioRequest $request, CrearUsuarioAction $action): RedirectResponse
    {
        $user = Auth::user();
        $esJefeDeArea = $request->unidadesDisponibles()->isNotEmpty();

        if ($esJefeDeArea) {
            $this->authorize('crearEnUnidad', [User::class, (int) $request->validated('unidad_organica_id')]);
        } else {
            $this->authorize('crearTrabajadorPropio', User::class);
        }

        try {
            $nuevo = $action->ejecutar($user, [...$request->validated(), 'tipo' => $request->input('tipo', 'trabajador')], $esJefeDeArea);
        } catch (UsuarioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $respuesta = redirect()->route('usuarios.index')
            ->with('success', "Usuario {$nuevo->nombre_completo} creado correctamente.");

        $advertencia = $request->input('tipo') === 'jefe_inmediato'
            ? null
            : $this->advertenciaTurnoSinJefe($nuevo, $request->input('turno'));

        return $advertencia ? $respuesta->with('warning', $advertencia) : $respuesta;
    }

    public function edit(User $trabajador): View
    {
        $user = Auth::user();
        $this->authorize('editar', $trabajador);

        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);

        $esJefeDeArea = $trabajador->unidad_organica_id && $unidadIds->contains($trabajador->unidad_organica_id);

        return view('usuarios.editar', [
            'trabajador' => $trabajador,
            'esJefeDeArea' => $esJefeDeArea,
            'unidades' => $esJefeDeArea
                ? UnidadOrganica::whereIn('id', $unidadIds)->orderBy('nombre')->get()
                : collect(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function update(EditarUsuarioRequest $request, User $trabajador, EditarUsuarioAction $action): RedirectResponse
    {
        $this->authorize('editar', $trabajador);

        $esJefeDeArea = $request->unidadesDisponibles()->isNotEmpty();

        $action->ejecutar($trabajador, $request->validated(), $esJefeDeArea);

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$trabajador->nombre_completo} actualizado correctamente.");
    }

    private function advertenciaTurnoSinJefe(User $nuevo, ?string $turno): ?string
    {
        if ($nuevo->regimen !== '728' || $turno !== null) {
            return null;
        }

        return $nuevo->nombre_completo.' es 728 y todavía no tiene turno: cárgalo en la programación de turnos; '
            .'hasta entonces no podrá crear papeletas.';
    }

    private function avisosDeUnidadesSinJefe(Collection $unidadIds): array
    {
        if ($unidadIds->isEmpty()) {
            return [];
        }

        return UnidadOrganica::with('jefe')
            ->whereIn('id', $unidadIds)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->filter(fn (UnidadOrganica $unidad) => ! $unidad->jefe?->activo)
            ->map(fn (UnidadOrganica $unidad) => "{$unidad->nombre}: sin jefe activo.")
            ->values()
            ->all();
    }

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
