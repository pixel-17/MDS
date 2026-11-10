<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Events\PapeletaActualizada;
use App\Livewire\Concerns\RequiereAdmin;
use App\Livewire\Usuarios\Concerns\TieneRolesDeUsuario;
use App\Models\ConfiguracionTurno;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use App\Support\ReglasDatosUsuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
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
class UsuarioAdminForm extends Component
{
    use RequiereAdmin, TieneRolesDeUsuario;

    #[Locked]
    public ?User $usuario = null;

    public string $name = '';

    public string $apellido = '';

    public string $dni = '';

    public string $email = '';

    public string $password = '';

    public string $regimen = '';

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    public array $rolesSeleccionados = [];

    public string $rolNuevo = '';

    public bool $activo = true;

    public string $turno = '';

    public string $fechaAncla = '';

    public int $diasTrabajo = 6;

    public int $diasDescanso = 1;

    public function mount(?User $usuario = null): void
    {
        if ($usuario?->exists) {
            $this->fechaAncla = now()->toDateString();
            $this->usuario = $usuario;
            $this->name = $usuario->name;
            $this->apellido = $usuario->apellido;
            $this->dni = $usuario->dni;
            $this->email = $usuario->email;
            $this->regimen = $usuario->regimen ?? '';
            $this->sedeId = $usuario->sede_id;
            $this->unidadOrganicaId = $usuario->unidad_organica_id;
            $this->rolesSeleccionados = self::aEnteros($usuario->roles->pluck('id')->all());
            $this->activo = $usuario->activo;

            $config = ConfiguracionTurno::where('user_id', $usuario->id)->first();

            if ($config) {
                $this->turno = $config->turno;
                $this->fechaAncla = $config->fecha_ancla->toDateString();
                $this->diasTrabajo = $config->dias_trabajo;
                $this->diasDescanso = $config->dias_descanso;
            }
        } else {
            $unidadId = request()->integer('unidad') ?: null;
            $unidad = $unidadId ? UnidadOrganica::with('jefe')->find($unidadId) : null;

            if ($unidad) {
                $this->unidadOrganicaId = $unidad->id;
                $this->sedeId = $unidad->jefe?->sede_id;
            }
        }
    }

    private static function idDelRol(string $nombre): int
    {
        $id = Role::where('name', $nombre)->value('id');

        return is_numeric($id) ? (int) $id : 0;
    }

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

    protected function esSoloRrhh(): bool
    {
        $rrhhId = self::idDelRol('rrhh');
        $roles = self::aEnteros($this->rolesSeleccionados);

        return $rrhhId > 0 && $roles !== [] && array_diff($roles, [$rrhhId]) === [];
    }

    protected function admiteConfiguracionTurno(): bool
    {
        if ($this->regimen !== '728') {
            return false;
        }

        if (! $this->usuario) {
            return true;
        }

        if ($this->cambioDeRegimen()) {
            return true;
        }

        return ! ConfiguracionTurno::where('user_id', $this->usuario->id)->exists();
    }

    protected function rules(): array
    {
        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => ReglasDatosUsuario::dni($this->usuario?->id, soloActivos: false),
            'email' => ReglasDatosUsuario::email($this->usuario?->id),
            'password' => ['nullable', 'string', 'min:8'],
            'regimen' => [($this->esSoloAdmin() || $this->esSoloRrhh()) ? 'nullable' : 'required', 'in:276,728'],
            'sedeId' => ['nullable', 'exists:sedes,id'],
            'unidadOrganicaId' => ['nullable', 'exists:unidad_organicas,id'],
            'rolesSeleccionados' => ['required', 'array', 'min:1'],
            'rolesSeleccionados.*' => ['exists:roles,id'],
            'activo' => ['boolean'],
        ];

        if ($this->admiteConfiguracionTurno() && $this->turno !== '') {
            $reglas['turno'] = ['required', 'in:'.implode(',', ConfiguracionTurno::turnosValidosPara(new User(['regimen' => $this->regimen])))];
            $reglas['fechaAncla'] = ['required', 'date'];
            $reglas['diasTrabajo'] = ['required', 'integer', 'min:1', 'max:30'];
            $reglas['diasDescanso'] = ['required', 'integer', 'min:1', 'max:30'];
        }

        return $reglas;
    }

    protected $messages = [
        'rolesSeleccionados.required' => 'Selecciona al menos un rol: un usuario sin rol no puede usar el sistema.',
        'rolesSeleccionados.min' => 'Selecciona al menos un rol: un usuario sin rol no puede usar el sistema.',
        'dni.digits' => 'El DNI debe tener 8 dígitos.',
        'dni.unique' => 'Ese DNI ya tiene una cuenta. Si está desactivada, reactívala desde Usuarios.',
    ];

    public function guardar(GeneradorTurnoMensualService $generador): void
    {
        $this->autorizarAdmin();

        if ($this->usuario === null && $this->rolNuevo !== '') {
            $this->rolesSeleccionados = [$this->rolNuevo];
        }

        if ($this->esSoloAdmin()) {
            $this->regimen = '';
            $this->sedeId = null;
            $this->unidadOrganicaId = null;
        } elseif ($this->esSoloRrhh()) {
            $this->regimen = '';
            $this->sedeId = null;
            $this->unidadOrganicaId = null;
        }

        $requiereTurno = $this->admiteConfiguracionTurno() && $this->turno !== '';

        $datos = $this->validate();

        $adminId = self::idDelRol('admin');
        $trabajadorId = self::idDelRol('trabajador');
        $roles = array_values(self::aEnteros($datos['rolesSeleccionados']));

        if (! $this->validarRoles($roles, $adminId, $trabajadorId)) {
            return;
        }

        if (! $this->validarUnidad($datos, $adminId, $roles)) {
            return;
        }

        if (! $this->validarUnicoRrhhActivo($datos)) {
            return;
        }

        if (! $this->validarUnicoAdminActivo($datos)) {
            return;
        }

        if (! $this->validarDesactivacion($datos)) {
            return;
        }

        $atributos = $this->construirAtributos($datos, $roles, $adminId);

        $debeRetirarTurno = $this->usuario !== null
            && ($this->cambioDeRegimen() || ($this->usuario->activo && ! $datos['activo']));

        DB::transaction(fn () => $this->guardarUsuario($atributos, $datos, $requiereTurno, $generador, $debeRetirarTurno));

        if ($this->usuario === null) {
            return;
        }

        session()->flash('mensaje', $this->usuario->wasRecentlyCreated ? 'Usuario creado.' : 'Usuario actualizado.');

        $this->redirectRoute('usuarios-admin.index', navigate: false);
    }

    /**
     * @param  list<int>  $roles
     */
    private function validarRoles(array $roles, int $adminId, int $trabajadorId): bool
    {
        if ($this->esSoloAdmin() && $this->regimen !== '') {
            $this->addError('rolesSeleccionados', 'Un administrador no lleva régimen: deja el régimen vacío.');

            return false;
        }

        if (in_array($adminId, $roles, true) && in_array($trabajadorId, $roles, true)) {
            $this->addError('rolesSeleccionados', 'Un administrador no puede tener también el rol de trabajador: no tiene papeletas, equipo ni jefe.');

            return false;
        }

        if (in_array($adminId, $roles, true) && $this->usuario?->unidadesQueEncabeza()->exists()) {
            $this->addError('rolesSeleccionados', 'Encabeza una unidad: reasigna primero esa jefatura antes de darle el rol de administrador.');

            return false;
        }

        if ($this->usuario === null) {
            $rrhhId = self::idDelRol('rrhh');
            $trabajadorId = self::idDelRol('trabajador');

            if (! in_array($adminId, $roles, true) && ! in_array($rrhhId, $roles, true)) {
                $this->addError('rolesSeleccionados', 'Desde Cuentas de usuario solo se crean administradores o RR. HH. puros. Para dar de alta trabajadores, hazlo desde el organigrama.');

                return false;
            }

            if (in_array($adminId, $roles, true) && in_array($rrhhId, $roles, true)) {
                $this->addError('rolesSeleccionados', 'Elige un solo rol: administrador o RR. HH.');

                return false;
            }

            if (in_array($rrhhId, $roles, true) && in_array($trabajadorId, $roles, true)) {
                $this->addError('rolesSeleccionados', 'Desde Cuentas de usuario solo se crea RR. HH. puro. Si la persona también es trabajador, dála de alta desde el organigrama y asígnale el rol RR. HH. desde allí.');

                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<int>  $roles
     */
    private function validarUnidad(array $datos, int $adminId, array $roles): bool
    {
        if (in_array($adminId, $roles, true)) {
            return true;
        }

        if ($this->usuario
            && $this->usuario->unidad_organica_id !== null
            && (is_numeric($datos['unidadOrganicaId']) ? (int) $datos['unidadOrganicaId'] : 0) !== (int) $this->usuario->unidad_organica_id
            && $this->usuario->unidadesQueEncabeza()->exists()
        ) {
            $this->addError('unidadOrganicaId', 'Encabeza una unidad: reasigna primero esa jefatura antes de moverlo a otra unidad.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarUnicoRrhhActivo(array $datos): bool
    {
        if (! $this->usuario?->esUnicoRrhhActivo()) {
            return true;
        }

        $rolRrhhId = self::idDelRol('rrhh');
        $conservaRol = in_array($rolRrhhId, self::aEnteros($datos['rolesSeleccionados']), true);

        if (! $datos['activo']) {
            $this->addError('activo', 'Es el único usuario de RR. HH. activo: sin él, toda papeleta aprobada por el jefe se autorizaría sola. Designa primero a otra persona con rol RR. HH.');

            return false;
        }

        if (! $conservaRol) {
            $this->addError('rolesSeleccionados', 'Es el único usuario de RR. HH. activo: no puedes quitarle ese rol hasta designar a otra persona.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarUnicoAdminActivo(array $datos): bool
    {
        if (! $this->usuario?->esUnicoAdminActivo()) {
            return true;
        }

        $rolAdminId = self::idDelRol('admin');
        $conservaRol = in_array($rolAdminId, self::aEnteros($datos['rolesSeleccionados']), true);

        if (! $datos['activo']) {
            $this->addError('activo', 'Es el único administrador activo: sin él nadie puede gestionar el sistema. Designa primero a otro administrador.');

            return false;
        }

        if (! $conservaRol) {
            $this->addError('rolesSeleccionados', 'Es el único administrador activo: no puedes quitarle ese rol hasta crear o activar a otro administrador.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarDesactivacion(array $datos): bool
    {
        if (! ($this->usuario?->activo && ! $datos['activo'])) {
            return true;
        }

        $actor = Auth::user();

        $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar($actor, $this->usuario);

        if ($motivo !== null) {
            $this->addError('activo', $motivo);

            return false;
        }

        $cargo = app(CambiarEstadoUsuarioAction::class)->jefaturasPorResolver($this->usuario);

        if ($cargo->isNotEmpty()) {
            $this->addError('activo', 'Tiene personas a cargo: desactívalo desde Usuarios para elegir a quién reemplaza.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<int>  $roles
     * @return array<string, mixed>
     */
    private function construirAtributos(array $datos, array $roles, int $adminId): array
    {
        return [
            'name' => $datos['name'],
            'apellido' => $datos['apellido'],
            'dni' => $datos['dni'],
            'email' => $datos['email'],
            'regimen' => $datos['regimen'] ?: null,
            'sede_id' => in_array($adminId, $roles, true) ? null : $datos['sedeId'],
            'unidad_organica_id' => in_array($adminId, $roles, true) ? null : $datos['unidadOrganicaId'],
            'activo' => $datos['activo'],
        ];
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<string, mixed>  $datos
     */
    private function guardarUsuario(array $atributos, array $datos, bool $requiereTurno, GeneradorTurnoMensualService $generador, bool $debeRetirarTurno): void
    {
        if ($this->usuario) {
            if (filled($datos['password'])) {
                $atributos['password'] = Hash::make(self::aTexto($datos['password']));
                $atributos['debe_actualizar_password'] = true;
            }

            $this->usuario->update($atributos);

            if (! $datos['activo']) {
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

        $this->usuario->syncRoles(self::aEnteros($datos['rolesSeleccionados']));
        PapeletaActualizada::olvidarDestinatariosFijos();

        if ($requiereTurno && $datos['activo']) {
            $actor = Auth::user();

            $generador->cargarConfiguracion(
                trabajador: $this->usuario,
                turno: self::aTexto($datos['turno']),
                fechaAncla: Carbon::parse(self::aTexto($datos['fechaAncla'])),
                actor: $actor,
                diasTrabajo: is_numeric($datos['diasTrabajo']) ? (int) $datos['diasTrabajo'] : 0,
                diasDescanso: is_numeric($datos['diasDescanso']) ? (int) $datos['diasDescanso'] : 0,
            );
        }
    }

    protected function buscarCuentaExistente(): ?User
    {
        if ($this->usuario !== null || strlen($this->dni) !== 8) {
            return null;
        }

        return User::where('dni', $this->dni)->first();
    }

    public function render(): View
    {
        return view('livewire.usuarios.usuario-admin-form', [
            'cuentaExistente' => $this->buscarCuentaExistente(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($s) => ['id' => $s->id, 'label' => $s->nombre])->all(),
            'unidades' => UnidadOrganica::orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($u) => ['id' => $u->id, 'label' => $u->nombre])->all(),
            'roles' => Role::orderBy('name')
                ->when($this->usuario === null, fn ($q) => $q->whereIn('name', ['admin', 'rrhh']))
                ->when($this->usuario !== null, fn ($q) => $q->where('name', '!=', 'admin'))
                ->get(),
            'esAdminRol' => $this->incluyeAdmin(),
            'esSoloRrhhRol' => $this->esSoloRrhh(),
        ]);
    }
}
