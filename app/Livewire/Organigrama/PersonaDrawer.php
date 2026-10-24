<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama;

use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Actions\Usuario\CrearUsuarioAction;
use App\Actions\Usuario\EditarUsuarioAction;
use App\Livewire\Concerns\ManejaExcepcionesDeUsuario;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Support\ReglasDatosUsuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class PersonaDrawer extends Component
{
    use ManejaExcepcionesDeUsuario;

    public bool $abierto = false;

    /** null = alta. */
    #[Locked]
    public ?int $personaId = null;

    public string $tipo = 'trabajador';

    public string $name = '';

    public string $apellido = '';

    public string $dni = '';

    public string $email = '';

    public string $regimen = '';

    public ?int $unidadId = null;

    public ?int $sedeId = null;

    public bool $activo = true;

    public ?string $error = null;

    /** @var Collection<int, UnidadOrganica>|null */
    private ?Collection $permitidasMemo = null;

    #[On('org-persona-abrir')]
    public function abrir(?int $id = null, ?int $unidadId = null, string $tipo = 'trabajador'): void
    {
        $actor = $this->actor();

        $this->reset(['name', 'apellido', 'dni', 'email', 'regimen', 'unidadId', 'sedeId', 'error']);
        $this->resetValidation();
        $this->personaId = null;
        $this->activo = true;
        $this->tipo = $tipo === 'jefe_inmediato' && $this->puedeCrearJefeInmediato($actor) ? 'jefe_inmediato' : 'trabajador';

        if ($id !== null) {
            $persona = User::findOrFail($id);
            $this->authorize('editar', $persona);

            $this->personaId = $persona->id;
            $this->name = $persona->name;
            $this->apellido = $persona->apellido;
            $this->dni = (string) $persona->dni;
            $this->email = $persona->email;
            $this->regimen = (string) $persona->regimen;
            $this->unidadId = $persona->unidad_organica_id;
            $this->sedeId = $persona->sede_id;
            $this->activo = (bool) $persona->activo;
        } elseif ($unidadId !== null && $this->unidadesPermitidas($actor)->contains('id', $unidadId)) {
            $this->unidadId = $unidadId;
            $this->sugerirRegimenDeLaUnidad();
        }

        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function elegirTipo(string $tipo): void
    {
        if ($this->personaId !== null) {
            return;
        }

        $this->tipo = $tipo === 'jefe_inmediato' && $this->puedeCrearJefeInmediato($this->actor()) ? 'jefe_inmediato' : 'trabajador';
        $this->resetValidation(['sedeId', 'regimen']);
    }

    private function puedeCrearJefeInmediato(User $actor): bool
    {
        return $actor->hasRole('admin') || $actor->esJefeDeArea();
    }

    public function updatedUnidadId(): void
    {
        if ($this->personaId === null) {
            $this->sugerirRegimenDeLaUnidad();
        }
    }

    public function guardar(): void
    {
        $actor = $this->actor();
        $this->error = null;

        if ($this->personaId === null) {
            $this->crear($actor);
        } else {
            $this->editar($actor);
        }
    }

    private function crear(User $actor): void
    {
        $permitidas = $this->unidadesPermitidas($actor);
        $existenteInactivo = User::where('dni', $this->dni)->where('activo', false)->first();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => ReglasDatosUsuario::dni(soloActivos: true),
            'email' => ReglasDatosUsuario::email($existenteInactivo?->id),
            'regimen' => ['required', Rule::in(['276', '728'])],
            'tipo' => ['required', Rule::in($this->puedeCrearJefeInmediato($actor) ? ['trabajador', 'jefe_inmediato'] : ['trabajador'])],
            'unidadId' => ['required', Rule::in($permitidas->pluck('id')->all())],
            'sedeId' => $this->tipo === 'jefe_inmediato' ? ['required', 'exists:sedes,id'] : ['nullable'],
        ]);

        $this->authorize('crearEnUnidad', [User::class, (int) $this->unidadId]);

        $destino = UnidadOrganica::with('jefe')->find($this->unidadId);
        $regimenDelJefe = $destino?->jefe?->regimen;

        if ($this->tipo === 'trabajador' && $regimenDelJefe && $regimenDelJefe !== $this->regimen) {
            $this->addError('regimen', "El régimen debe coincidir con el de su jefe inmediato (régimen {$regimenDelJefe}).");

            return;
        }

        $nuevo = null;

        if (! $this->intentar(function () use ($actor, &$nuevo) {
            $nuevo = app(CrearUsuarioAction::class)->ejecutar($actor, [
                'name' => $this->name,
                'apellido' => $this->apellido,
                'dni' => $this->dni,
                'email' => $this->email,
                'regimen' => $this->regimen,
                'sede_id' => $this->tipo === 'jefe_inmediato' ? $this->sedeId : null,
                'unidad_organica_id' => $this->unidadId,
                'tipo' => $this->tipo,
                'turno' => null,
                'fecha_ancla' => null,
                'dias_trabajo' => null,
                'dias_descanso' => null,
            ], true);
        }, fn ($e) => $this->error = $e->getMessage())) {
            return;
        }

        $mensaje = $this->tipo === 'jefe_inmediato'
            ? "Se creó a {$nuevo->nombre_completo} como jefe inmediato de {$destino->nombre}."
            : "Se creó a {$nuevo->nombre_completo}.";

        if ($this->tipo === 'trabajador' && $nuevo->regimen === '728') {
            $mensaje .= ' Es 728 y aún no tiene turno: cárgalo en la programación de turnos.';
        }

        $this->abierto = false;
        $this->dispatch('org-actualizado', mensaje: $mensaje, personaId: $nuevo->id);
    }

    private function editar(User $actor): void
    {
        $persona = User::find($this->personaId);

        if (! $persona) {
            $this->error = 'La persona ya no existe.';

            return;
        }

        $this->authorize('editar', $persona);

        $esAdmin = $actor->hasRole('admin');
        $encabeza = $persona->unidadesQueEncabeza()->exists();
        $cambiaUnidad = ! $encabeza
            && $this->unidadId !== null
            && (int) $this->unidadId !== (int) $persona->unidad_organica_id;

        $this->validate($this->reglasDeEdicion($actor, $persona, $esAdmin, $cambiaUnidad));

        $activo = $esAdmin ? $this->activo : (bool) $persona->activo;
        $estado = app(CambiarEstadoUsuarioAction::class);

        if (! $this->puedeDesactivar($actor, $persona, $estado, $activo)) {
            return;
        }

        if (! $this->intentar(
            fn () => $this->aplicarCambiosDePersona($actor, $persona, $estado, $cambiaUnidad, $esAdmin, $activo),
            fn ($e) => $this->error = $e->getMessage(),
        )) {
            return;
        }

        $this->abierto = false;
        $this->dispatch('org-actualizado', mensaje: "Datos de {$persona->nombre_completo} actualizados.", personaId: $persona->id);
    }

    /** @return array<string, array<int, mixed>|string> */
    private function reglasDeEdicion(User $actor, User $persona, bool $esAdmin, bool $cambiaUnidad): array
    {
        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ReglasDatosUsuario::email($persona->id),

            'sedeId' => $esAdmin ? ['nullable', 'exists:sedes,id'] : ['required', 'exists:sedes,id'],
            'unidadId' => ['nullable', 'exists:unidad_organicas,id'],
            'activo' => ['boolean'],
        ];

        if ($cambiaUnidad) {
            $reglas['unidadId'][] = Rule::in($this->unidadesPermitidas($actor)->pluck('id')->all());
        }

        return $reglas;
    }

    private function puedeDesactivar(User $actor, User $persona, CambiarEstadoUsuarioAction $estado, bool $activo): bool
    {
        if (! ($persona->activo && ! $activo)) {
            return true;
        }

        $motivo = $estado->motivoParaNoDesactivar($actor, $persona);

        if ($motivo !== null) {
            $this->error = $motivo;

            return false;
        }

        if ($estado->jefaturasPorResolver($persona)->isNotEmpty()) {
            $this->abierto = false;
            $this->dispatch('usuario-baja-abrir', id: $persona->id);

            return false;
        }

        return true;
    }

    private function aplicarCambiosDePersona(User $actor, User $persona, CambiarEstadoUsuarioAction $estado, bool $cambiaUnidad, bool $esAdmin, bool $activo): void
    {

        if ($cambiaUnidad) {
            $mover = app(MoverTrabajadorAction::class);

            if ($persona->unidad_organica_id === null) {

                $mover->exigirJefeInmediato(UnidadOrganica::findOrFail($this->unidadId));
                $persona->unidad_organica_id = $this->unidadId;
                $persona->save();
            } else {
                $mover->ejecutar($actor, $persona->id, (int) $this->unidadId, (int) $persona->unidad_organica_id, true, false);
            }

            $persona->refresh();
        }

        app(EditarUsuarioAction::class)->ejecutar($persona, [
            'name' => $this->name,
            'apellido' => $this->apellido,
            'email' => $this->email,
            'sede_id' => $this->sedeId,
            'unidad_organica_id' => $persona->unidad_organica_id,
        ], true);

        if ($esAdmin) {
            $persona->forceFill(['sede_id' => $this->sedeId])->save();
        }

        if ($persona->activo && ! $activo) {
            $estado->desactivar($actor, $persona);
        } elseif (! $persona->activo && $activo) {
            $estado->reactivar($actor, $persona);
        }
    }

    public function render(): View
    {
        if (! $this->abierto) {
            return view('livewire.organigrama.persona-drawer', ['abierto' => false]);
        }

        $actor = $this->actor();
        $permitidas = $this->unidadesPermitidas($actor);
        $persona = $this->personaId
            ? User::with(['jefeInmediato', 'sede', 'unidadOrganica'])->find($this->personaId)
            : null;

        $destino = $this->unidadId ? UnidadOrganica::with('jefe.sede')->find($this->unidadId) : null;

        $unidades = $permitidas
            ->map(fn (UnidadOrganica $u) => ['id' => (int) $u->id, 'label' => $u->nombre, 'hint' => $u->jefe?->nombre_completo ?? 'sin jefe'])
            ->values();

        if ($persona?->unidadOrganica && ! $unidades->contains('id', (int) $persona->unidad_organica_id)) {
            $unidades->push(['id' => (int) $persona->unidad_organica_id, 'label' => $persona->unidadOrganica->nombre, 'hint' => null]);
        }

        return view('livewire.organigrama.persona-drawer', [
            'abierto' => true,
            'creando' => $this->personaId === null,
            'persona' => $persona,
            'esAdmin' => $actor->hasRole('admin'),
            'puedeCrearJefeInmediato' => $this->puedeCrearJefeInmediato($actor),
            'destino' => $destino,
            'unidades' => $unidades->all(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'label' => $s->nombre, 'hint' => null])->all(),
            'encabeza' => $persona ? $persona->unidadesQueEncabeza()->orderBy('nombre')->pluck('nombre') : collect(),

            'sedeHeredada' => $destino ? ($destino->jefe?->sede?->nombre ?? $actor->sede?->nombre ?? 'Sin sede') : null,
        ]);
    }

    private function sugerirRegimenDeLaUnidad(): void
    {
        if ($this->tipo !== 'trabajador' || $this->regimen !== '' || $this->unidadId === null) {
            return;
        }

        $regimen = UnidadOrganica::with('jefe')->find($this->unidadId)?->jefe?->regimen;

        if ($regimen) {
            $this->regimen = (string) $regimen;
        }
    }

    private function actor(): User
    {

        $usuario = auth()->user();

        abort_unless($usuario && ($usuario->hasRole('admin') || $usuario->esJefeDeEquipo()), 403);

        return $usuario;
    }

    private function unidadesPermitidas(User $actor): Collection
    {
        if ($this->permitidasMemo !== null) {
            return $this->permitidasMemo;
        }

        $consulta = UnidadOrganica::with('jefe.sede')->where('activo', true)->orderBy('nombre');

        if (! $actor->hasRole('admin')) {
            $ids = collect();

            foreach ($actor->unidadesQueEncabeza as $unidad) {
                $ids->push($unidad->id);
                $ids = $ids->merge($unidad->descendantIds());
            }

            $consulta->whereIn('id', $ids->unique()->values()->all());
        }

        return $this->permitidasMemo = $consulta->get();
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombres',
            'apellido' => 'apellidos',
            'dni' => 'DNI',
            'email' => 'correo',
            'regimen' => 'régimen',
            'unidadId' => 'unidad',
            'sedeId' => 'sede',
        ];
    }

    protected function messages(): array
    {
        return [
            'dni.digits' => 'El DNI debe tener 8 dígitos.',
            'dni.unique' => 'Ya existe un usuario activo con ese DNI.',
            'email.unique' => 'Ese correo ya está en uso por otro usuario.',
            'unidadId.required' => 'Elige la unidad.',
            'unidadId.in' => 'Esa unidad no pertenece a tu área.',
            'sedeId.required' => 'Elige la sede: es necesaria para los cierres.',
        ];
    }
}
