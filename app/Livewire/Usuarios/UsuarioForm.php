<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Actions\Usuario\CrearUsuarioAction;
use App\Actions\Usuario\EditarUsuarioAction;
use App\Events\PapeletaActualizada;
use App\Exceptions\UsuarioException;
use App\Livewire\Usuarios\Concerns\TieneRolesDeUsuario;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use App\Support\ReglasDatosUsuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Usuario')]
class UsuarioForm extends Component
{
    use TieneRolesDeUsuario;

    #[Locked]
    public ?User $usuario = null;

    #[Locked]
    public string $alcance = 'admin';

    public string $name = '';

    public string $apellido = '';

    public string $dni = '';

    public string $email = '';

    public string $password = '';

    public string $regimen = '';

    public string $turno = '';

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    /**
     * @var array<int, int|string>
     */
    public array $rolesSeleccionados = [];

    public bool $activo = true;

    public string $tipo = 'trabajador';

    #[Locked]
    public bool $nuevoAdmin = false;

    #[Locked]
    public bool $volverAlOrganigrama = false;

    public function mount(?User $usuario = null): void
    {
        $existente = $usuario?->exists ? $usuario : null;

        $this->alcance = $this->resolverAlcance($existente);
        $this->volverAlOrganigrama = request()->query('volver') === 'organigrama';

        if ($existente) {
            $this->usuario = $existente;
            $this->name = $existente->name;
            $this->apellido = $existente->apellido;
            $this->dni = $existente->dni;
            $this->email = $existente->email;
            $this->regimen = $existente->regimen ?? '';
            $this->sedeId = $existente->sede_id;
            $this->unidadOrganicaId = $existente->unidad_organica_id;
            $this->rolesSeleccionados = self::aEnteros($existente->roles->pluck('id')->all());
            $this->activo = $existente->activo;

            return;
        }

        if ($this->esAdmin() && request()->query('tipo') === 'admin') {
            $this->nuevoAdmin = true;
            $adminId = self::idDelRol('admin');
            $this->rolesSeleccionados = $adminId > 0 ? [(string) $adminId] : [];

            return;
        }

        if ($this->esEquipo()) {
            return;
        }

        $unidadId = request()->integer('unidad') ?: null;

        if ($unidadId && $this->esArea() && ! in_array($unidadId, $this->idsDeArea(), true)) {
            $unidadId = null;
        }

        $unidad = $unidadId ? UnidadOrganica::with('jefe')->find($unidadId) : null;

        if ($unidad) {
            $this->unidadOrganicaId = $unidad->id;
            $this->sedeId = $unidad->jefe?->sede_id;
        }

        if ($this->esArea()) {
            $this->regimen = (string) (Auth::user()->regimen ?? '');
            $this->sedeId ??= Auth::user()?->sede_id;
        }
    }

    protected function resolverAlcance(?User $objetivo): string
    {
        /**
         * @var User $actor
         */
        $actor = Auth::user();

        if ($actor->hasRole('admin') && ($objetivo || request()->query('tipo') === 'admin' || $this->nuevoAdmin)) {
            return 'admin';
        }

        if ($objetivo) {
            abort_unless($actor->can('editar', $objetivo), 403);

            return $objetivo->unidad_organica_id
                && in_array((int) $objetivo->unidad_organica_id, $this->idsDeArea($actor), true)
                    ? 'area'
                    : 'equipo';
        }

        if ($actor->esJefeDeArea()) {
            return 'area';
        }

        abort_unless($actor->can('puedeCrearAlgo', User::class), 403);

        return 'equipo';
    }

    private static function idDelRol(string $nombre): int
    {
        $id = Role::where('name', $nombre)->value('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * @return array<int, int>
     */
    private static function aEnteros(mixed $valores): array
    {
        if (! is_array($valores)) {
            return [];
        }

        return array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $valores);
    }

    private static function aTexto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    protected function esAdmin(): bool
    {
        return $this->alcance === 'admin';
    }

    protected function esArea(): bool
    {
        return $this->alcance === 'area';
    }

    protected function esEquipo(): bool
    {
        return $this->alcance === 'equipo';
    }

    /**
     * @return array<int, int>
     */
    protected function idsDeArea(?User $actor = null): array
    {
        $actor ??= Auth::user();

        if ($actor === null) {
            return [];
        }

        $ids = collect();

        foreach ($actor->unidadesDeArea as $u) {
            $ids->push($u->id);
            $ids = $ids->merge($u->descendantIds());
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $creando = $this->usuario === null;

        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ReglasDatosUsuario::email($this->usuario?->id),
        ];

        if ($creando || ! $this->esEquipo()) {
            $reglas['dni'] = ReglasDatosUsuario::dni(
                $this->usuario?->id,
                soloActivos: $creando && ! $this->esAdmin(),
            );
        }

        if (! $this->esEquipo() || $creando) {
            $reglas['regimen'] = [
                $this->esAdmin() && $this->esSoloAdmin() ? 'nullable' : 'required',
                'in:276,728',
            ];
        }

        if ($this->esAdmin()) {
            $reglas['password'] = ['nullable', 'string', 'min:8'];
            $reglas['sedeId'] = ['nullable', 'exists:sedes,id'];
            $reglas['unidadOrganicaId'] = ['nullable', 'exists:unidad_organicas,id'];
            $reglas['rolesSeleccionados'] = ['required', 'array', 'min:1'];
            $reglas['rolesSeleccionados.*'] = ['exists:roles,id'];
            $reglas['activo'] = ['boolean'];
        }

        if ($this->esArea()) {
            $idsArea = $this->idsDeArea();
            $reglas['unidadOrganicaId'] = ['required', 'in:'.implode(',', $idsArea ?: [0])];
            $reglas['sedeId'] = ($creando && $this->tipo === 'trabajador')
                ? ['nullable', 'exists:sedes,id']
                : ['required', 'exists:sedes,id'];

            if ($creando) {
                $reglas['tipo'] = ['required', 'in:trabajador,jefe_inmediato'];
            } else {
                $reglas['activo'] = ['boolean'];
            }
        }

        if ($this->esEquipo() && ! $creando) {
            $reglas['activo'] = ['boolean'];
            $reglas['sedeId'] = ['nullable', 'exists:sedes,id'];
            $reglas['regimen'] = ['required', 'in:276,728'];
        }

        return $reglas;
    }

    /**
     * @var array<string, string>
     */
    protected $messages = [
        'rolesSeleccionados.required' => 'Selecciona al menos un rol: un usuario sin rol no puede usar el sistema.',
        'rolesSeleccionados.min' => 'Selecciona al menos un rol: un usuario sin rol no puede usar el sistema.',
        'dni.digits' => 'El DNI debe tener 8 dígitos.',
        'dni.unique' => 'Ese DNI ya tiene una cuenta. Si está desactivada, reactívala desde Usuarios.',
        'unidadOrganicaId.in' => 'Esa unidad no pertenece a tu área.',
        'sedeId.required' => 'Indica la sede: es necesaria para los cierres.',
    ];

    public function guardar(GeneradorTurnoMensualService $generador): void
    {
        $this->alcance = $this->resolverAlcance($this->usuario);

        $datos = $this->validate();

        if ($this->usuario === null && ! $this->esAdmin()) {
            $this->guardarComoJefe($datos);

            return;
        }

        if ($this->esEquipo()) {
            $this->guardarComoJefeInmediato($datos);

            return;
        }

        $this->guardarComoAdminOArea($datos, $generador);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function guardarComoJefe(array $datos): void
    {
        /**
         * @var User $actor
         */
        $actor = Auth::user();
        $esArea = $this->esArea();

        if ($esArea) {
            abort_unless($actor->can('crearEnUnidad', [User::class, (int) $this->unidadOrganicaId]), 403);
        } else {
            abort_unless($actor->can('puedeCrearAlgo', User::class), 403);
        }

        try {
            $nuevo = app(CrearUsuarioAction::class)->ejecutar($actor, [
                'name' => self::aTexto($datos['name']),
                'apellido' => self::aTexto($datos['apellido']),
                'dni' => self::aTexto($datos['dni']),
                'email' => self::aTexto($datos['email']),
                'regimen' => self::aTexto($datos['regimen']),
                'sede_id' => $esArea ? $this->sedeId : null,
                'unidad_organica_id' => $esArea ? $this->unidadOrganicaId : null,
                'tipo' => $esArea ? $this->tipo : 'trabajador',
                'turno' => null,
                'fecha_ancla' => null,
                'dias_trabajo' => null,
                'dias_descanso' => null,
            ], $esArea);
        } catch (UsuarioException $e) {
            $this->addError('dni', $e->getMessage());

            return;
        }

        session()->flash('mensaje', "Usuario {$nuevo->nombre_completo} creado correctamente.");

        if ($nuevo->regimen === '728' && $this->tipo !== 'jefe_inmediato') {
            session()->flash('warning', "{$nuevo->nombre_completo} es 728 y todavía no tiene turno: cárgalo en Turnos; hasta entonces no podrá crear papeletas.");
        }

        $this->redirectRoute('usuarios.index', navigate: false);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function guardarComoJefeInmediato(array $datos): void
    {
        if ($this->usuario === null) {
            return;
        }

        $usuario = $this->usuario;

        /**
         * @var User $actor
         */
        $actor = Auth::user();

        abort_unless($actor->can('editar', $usuario), 403);

        $activo = (bool) ($datos['activo'] ?? $usuario->activo);

        if ($usuario->activo && ! $activo) {
            $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar($actor, $usuario);

            if ($motivo !== null) {
                $this->addError('activo', $motivo);

                return;
            }

            $cargo = app(CambiarEstadoUsuarioAction::class)->jefaturasPorResolver($usuario);

            if ($cargo->isNotEmpty()) {
                $this->addError('activo', 'Tiene personas a cargo: desactívalo desde Usuarios para elegir su reemplazo.');

                return;
            }
        }

        $debeRetirarTurno = $usuario->activo && ! $activo;

        DB::transaction(function () use ($datos, $activo, $debeRetirarTurno, $usuario) {
            app(EditarUsuarioAction::class)->ejecutar($usuario, [
                'name' => self::aTexto($datos['name']),
                'apellido' => self::aTexto($datos['apellido']),
                'email' => self::aTexto($datos['email']),
                'sede_id' => $usuario->sede_id,
                'unidad_organica_id' => $usuario->unidad_organica_id,
            ], false);

            $usuario->update([
                'sede_id' => $datos['sedeId'] ?? $usuario->sede_id,
                'regimen' => $datos['regimen'] ?? $usuario->regimen,
            ]);

            $usuario->update(['activo' => $activo]);

            if (! $activo) {
                $usuario->tokens()->delete();
            }

            if ($debeRetirarTurno) {
                app(GeneradorTurnoMensualService::class)->retirarTurno($usuario);
            }
        });

        session()->flash('mensaje', "Usuario {$usuario->nombre_completo} actualizado.");

        $this->redirectRoute('usuarios.index', navigate: false);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function guardarComoAdminOArea(array $datos, GeneradorTurnoMensualService $generador): void
    {
        $esAdmin = $this->esAdmin();
        $adminId = self::idDelRol('admin');
        $trabajadorId = self::idDelRol('trabajador');

        $roles = $esAdmin
            ? self::aEnteros($datos['rolesSeleccionados'])
            : self::aEnteros(($this->usuario->roles ?? collect())->pluck('id')->all());

        $activo = ($esAdmin || $this->esArea())
            ? (bool) ($datos['activo'] ?? $this->usuario->activo ?? true)
            : true;

        if ($esAdmin) {
            if ($this->esSoloAdmin() && $this->regimen !== '') {
                $this->addError('rolesSeleccionados', 'Un administrador no lleva régimen: deja el campo vacío.');

                return;
            }

            if (in_array($adminId, $roles, true) && in_array($trabajadorId, $roles, true)) {
                $this->addError('rolesSeleccionados', 'Un administrador no puede tener también el rol de trabajador.');

                return;
            }

            if (in_array($adminId, $roles, true) && $this->usuario?->unidadesQueEncabeza()->exists()) {
                $this->addError('rolesSeleccionados', 'Encabeza una unidad: reasigna primero esa jefatura antes de darle el rol de administrador.');

                return;
            }

            if ($this->usuario?->esUnicoRrhhActivo()) {
                $rolRrhhId = self::idDelRol('rrhh');

                if (! $activo) {
                    $this->addError('activo', 'Es el único usuario de RR. HH. activo. Designa primero a otra persona.');

                    return;
                }

                if (! in_array($rolRrhhId, $roles, true)) {
                    $this->addError('rolesSeleccionados', 'Es el único usuario de RR. HH. activo: no puedes quitarle ese rol.');

                    return;
                }
            }

            if ($this->usuario?->esUnicoAdminActivo()) {
                if (! $activo) {
                    $this->addError('activo', 'Es el único administrador activo. Designa primero a otro.');

                    return;
                }

                if (! in_array($adminId, $roles, true)) {
                    $this->addError('rolesSeleccionados', 'Es el único administrador activo: no puedes quitarle ese rol.');

                    return;
                }
            }
        }

        if ($this->usuario
            && $this->usuario->unidad_organica_id !== null
            && (is_numeric($datos['unidadOrganicaId']) ? (int) $datos['unidadOrganicaId'] : 0) !== (int) $this->usuario->unidad_organica_id
            && $this->usuario->unidadesQueEncabeza()->exists()
        ) {
            $this->addError('unidadOrganicaId', 'Encabeza una unidad: reasigna primero esa jefatura antes de moverlo a otra unidad.');

            return;
        }

        if ($this->usuario?->activo && ! $activo) {
            /**
             * @var User $actor
             */
            $actor = Auth::user();

            $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar($actor, $this->usuario);

            if ($motivo !== null) {
                $this->addError('activo', $motivo);

                return;
            }

            $cargo = app(CambiarEstadoUsuarioAction::class)->jefaturasPorResolver($this->usuario);

            if ($cargo->isNotEmpty()) {
                $this->addError('activo', 'Tiene personas a cargo: desactívalo desde Usuarios para elegir su reemplazo.');

                return;
            }
        }

        $atributos = [
            'name' => $datos['name'],
            'apellido' => $datos['apellido'],
            'email' => $datos['email'],
            'activo' => $activo,
        ];

        if (! $this->esArea() || $this->usuario === null) {
            $atributos['dni'] = $datos['dni'];
            $atributos['regimen'] = $datos['regimen'] ?: null;
            $atributos['sede_id'] = $datos['sedeId'];
            $atributos['unidad_organica_id'] = in_array($adminId, $roles, true) ? null : $datos['unidadOrganicaId'];
        } else {
            $atributos['sede_id'] = $datos['sedeId'];
            $atributos['unidad_organica_id'] = $datos['unidadOrganicaId'];
            $atributos['regimen'] = $datos['regimen'] ?: null;
        }

        $debeRetirarTurno = $this->usuario !== null
            && ($this->cambioDeRegimen() || ($this->usuario->activo && ! $activo));

        DB::transaction(function () use ($atributos, $datos, $generador, $debeRetirarTurno, $esAdmin, $activo, $roles) {
            if ($this->usuario) {
                if ($esAdmin && filled($datos['password'] ?? null)) {
                    $atributos['password'] = Hash::make(self::aTexto($datos['password']));
                    $atributos['debe_actualizar_password'] = true;
                }

                $this->usuario->update($atributos);

                if (! $activo) {
                    $this->usuario->tokens()->delete();
                }

                if ($debeRetirarTurno) {
                    $generador->retirarTurno($this->usuario);
                }
            } else {
                $atributos['password'] = Hash::make(self::aTexto($datos['dni']));
                $atributos['debe_actualizar_password'] = true;

                $this->usuario = User::create($atributos);
            }

            if ($esAdmin) {
                $this->usuario->syncRoles($roles);
                PapeletaActualizada::olvidarDestinatariosFijos();
            }
        });

        if ($this->usuario === null) {
            return;
        }

        session()->flash('mensaje', $this->usuario->wasRecentlyCreated ? 'Usuario creado.' : 'Usuario actualizado.');

        $this->redirectRoute($esAdmin ? 'usuarios-admin.index' : 'usuarios.index', navigate: false);
    }

    protected function sedeHeredada(): ?string
    {
        if ($this->usuario !== null || ! $this->esArea() || $this->tipo !== 'trabajador') {
            return null;
        }

        return UnidadOrganica::with('jefe.sede')->find($this->unidadOrganicaId)?->jefe?->sede->nombre
            ?? Auth::user()?->sede->nombre
            ?? 'Sin sede';
    }

    protected function buscarCuentaExistente(): ?User
    {
        if (! $this->esAdmin() || $this->usuario !== null || strlen($this->dni) !== 8) {
            return null;
        }

        return User::where('dni', $this->dni)->first();
    }

    public function render(): View
    {
        $unidades = match (true) {
            $this->esEquipo() => collect(),
            $this->esArea() => UnidadOrganica::orderBy('nombre')->whereIn('id', $this->idsDeArea())->pluck('nombre', 'id'),
            default => UnidadOrganica::orderBy('nombre')->pluck('nombre', 'id'),
        };

        return view('livewire.usuarios.usuario-form', [
            'cuentaExistente' => $this->buscarCuentaExistente(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'),
            'unidades' => $unidades,
            'roles' => $this->esAdmin()
                ? Role::orderBy('name')
                    ->when($this->usuario === null && ! $this->nuevoAdmin, fn ($q) => $q->where('name', '!=', 'admin'))
                    ->get()
                : collect(),
            'esAdminRol' => $this->esAdmin() && $this->incluyeAdmin(),
            'sedeHeredada' => $this->sedeHeredada(),
            'sedeDelCreador' => Auth::user()?->sede?->nombre,
        ]);
    }
}
