<?php

namespace App\Models;


use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $apellido
 * @property string $dni
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property '276'|'728'|null $regimen
 * @property bool $activo
 * @property int|null $sede_id
 * @property int|null $jefe_inmediato_id
 * @property int|null $jefe_area_id
 * @property int|null $unidad_organica_id
 * @property bool $debe_actualizar_password
 * @property int $volumen_notificacion
 * @property bool $permite_gps
 * @property bool $permite_camara
 * @property-read string $nombre_completo
 * @property-read Sede|null $sede
 * @property-read UnidadOrganica|null $unidadOrganica
 * @property-read User|null $jefeInmediato
 * @property-read User|null $jefeArea
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UnidadOrganica> $unidadesQueEncabeza
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $subordinadosInmediatos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Turno> $turnos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Papeleta> $papeletas
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasPushSubscriptions;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'apellido',
        'dni',
        'email',
        'password',
        'debe_actualizar_password',
        'regimen',
        'activo',
        'sede_id',
        'jefe_inmediato_id',
        'jefe_area_id',
        'unidad_organica_id',
        'volumen_notificacion',
        'permite_gps',
        'permite_camara',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'debe_actualizar_password' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<UnidadOrganica, $this>
     */
    public function unidadOrganica(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class);
    }

    
    /**
     * @return BelongsTo<User, $this>
     */
    public function jefeInmediato(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_id');
    }

    
    /**
     * @return BelongsTo<User, $this>
     */
    public function jefeArea(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_id');
    }

    
    /**
     * @return HasMany<UnidadOrganica, $this>
     */
    public function unidadesQueEncabeza(): HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'jefe_id');
    }

    /**
     * Unidades que este usuario encabeza Y que tienen sub-unidades: solo
     * esas lo hacen Jefe de Área. Quien encabeza una unidad hoja (sin
     * sub-oficinas) es Jefe Inmediato "puro": su jefe_id lo pone como
     * jefe de ESA oficina, no le da mando sobre otras.
     *
     * @return HasMany<UnidadOrganica, $this>
     */
    public function unidadesDeArea(): HasMany
    {
        return $this->unidadesQueEncabeza()->whereHas('hijos');
    }

    
    public function esJefeTitular(): bool
    {
        return $this->unidadesQueEncabeza()->exists();
    }

    
    public const TIPOS_USUARIO = [
        'admin' => 'Administrador',
        'rrhh' => 'RRHH',
        'jefe_area' => 'Jefe de área',
        'jefe_inmediato' => 'Jefe inmediato',
        'trabajador' => 'Trabajador',
    ];

    /** @var 'admin'|'rrhh'|'jefe_area'|'jefe_inmediato'|'trabajador'|null */
    private ?string $tipoUsuarioMemo = null;

    /**
     * Clave del tipo de usuario para mostrarlo en pantalla (la más alta que
     * aplique): admin > rrhh > jefe de área > jefe inmediato > trabajador.
     * Es solo una etiqueta visual; los permisos siguen en policies y roles.
     * Se memoiza por instancia (hasta 3 consultas la primera vez).
     *
     * @return 'admin'|'rrhh'|'jefe_area'|'jefe_inmediato'|'trabajador'
     */
    public function tipoUsuario(): string
    {
        return $this->tipoUsuarioMemo ??= match (true) {
            $this->hasRole('admin') => 'admin',
            $this->hasRole('rrhh') => 'rrhh',
            $this->esJefeDeArea() => 'jefe_area',
            $this->esJefeTitular() => 'jefe_inmediato',
            default => 'trabajador',
        };
    }

    public function etiquetaTipoUsuario(): string
    {
        return self::TIPOS_USUARIO[$this->tipoUsuario()];
    }

    
    public function esJefeDeArea(): bool
    {
        return $this->unidadesDeArea()->exists();
    }

    
    /**
     * @return HasMany<User, $this>
     */
    public function subordinadosInmediatos(): HasMany
    {
        return $this->hasMany(User::class, 'jefe_inmediato_id');
    }

    
    public function esJefeDeEquipo(): bool
    {
        if ($this->hasRole('admin')) {
            return false;
        }

        return $this->unidadesQueEncabeza()->exists()
            || static::where('jefe_inmediato_id', $this->id)->exists();
    }

    /**
     * Usuarios que son jefe de alguien: encabezan una unidad o figuran como
     * jefe inmediato de otro usuario. Misma regla que esJefeDeAlguien(), pero
     * resuelta en SQL para filtrar listas enteras sin 2 consultas por fila.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeQueEsJefe(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereHas('unidadesQueEncabeza')
            ->orWhereIn('users.id', User::query()
                ->select('jefe_inmediato_id')
                ->whereNotNull('jefe_inmediato_id')));
    }

    /**
     * Fuente ÚNICA de "mi equipo": trabajadores cuyo jefe inmediato o de
     * área (columnas explícitas) soy yo y todos los de las unidades que
     * encabezo (y sus sub-unidades).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeEquipoDe(Builder $query, User $jefe): Builder
    {
        $unidadIds = $jefe->unidadesQueEncabeza
            ->flatMap(fn ($u) => collect([$u->id])->merge($u->descendantIds()))
            ->unique()
            ->values();

        return $query->where(function ($q) use ($jefe, $unidadIds) {
            $q->where('users.jefe_inmediato_id', $jefe->id)
                ->orWhere('users.jefe_area_id', $jefe->id);

            if ($unidadIds->isNotEmpty()) {
                $q->orWhereIn('users.unidad_organica_id', $unidadIds);
            }
        });
    }

    /**
     * Equipo del jefe como colección (para selects/buscadores).
     *
     * @return Collection<int, User>
     */
    public function equipo(): Collection
    {
        return User::equipoDe($this)->orderBy('name')->get();
    }

    /**
     * @deprecated usar equipo() / User::equipoDe(); se mantiene por compatibilidad.
     *
     * @return Collection<int, User>
     */
    public function trabajadoresComoJefeInmediato(): Collection
    {
        return $this->equipo();
    }

    /**
     * @deprecated usar equipo() / User::equipoDe(); se mantiene por compatibilidad.
     *
     * @return Collection<int, User>
     */
    public function trabajadoresParaReportes(): Collection
    {
        return $this->equipo();
    }

    
    public function esUnicoRrhhActivo(): bool
    {
        return $this->activo
            && $this->hasRole('rrhh')
            && ! static::role('rrhh')->where('activo', true)->whereKeyNot($this->getKey())->exists();
    }

    
    public function esUnicoAdminActivo(): bool
    {
        return $this->activo
            && $this->hasRole('admin')
            && ! static::role('admin')->where('activo', true)->whereKeyNot($this->getKey())->exists();
    }

    
    public function esJefeInmediatoDe(User $trabajador): bool
    {
        return $trabajador->jefe_inmediato_id !== null
            && (int) $trabajador->jefe_inmediato_id === (int) $this->id;
    }

    /**
     * Colección con el jefe inmediato de este usuario (0 o 1). Se mantiene
     * como colección para las vistas que listan "mis jefes".
     *
     * @return Collection<int, User>
     */
    public function jefesInmediatos(): Collection
    {
        return $this->jefe_inmediato_id === null
            ? collect()
            : User::whereKey($this->jefe_inmediato_id)->get();
    }

    /**
     * @return HasMany<Turno, $this>
     */
    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }

    
    public function puedeGestionarTurnoDe(User $trabajador): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->id === $trabajador->id) {
            return $this->esJefeDeAlguien();
        }

        
        
        if ($this->esJefeParDe($trabajador)) {
            return false;
        }

        if ($trabajador->jefe_area_id === $this->id) {
            return true;
        }

        
        if (! $this->esJefeDeArea() && $trabajador->esJefeDeAlguien()) {
            return false;
        }

        return $this->esJefeInmediatoDe($trabajador);
    }

    
    public function esJefeParDe(User $otro): bool
    {
        if ($this->id === $otro->id) {
            return false;
        }

        $mias = $this->unidadIdsComoJefe();

        return $mias->isNotEmpty() && $otro->unidadIdsComoJefe()->intersect($mias)->isNotEmpty();
    }

    /** @return Collection<int, int> */
    private function unidadIdsComoJefe(): Collection
    {
        return UnidadOrganica::where('jefe_id', $this->id)->pluck('id');
    }

    
    public function esJefeDeAlguien(): bool
    {
        return $this->unidadesQueEncabeza()->exists()
            || User::where('jefe_inmediato_id', $this->id)->exists();
    }

    /**
     * @return HasMany<Papeleta, $this>
     */
    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class, 'trabajador_id');
    }

    
    /** @return Attribute<string, never> */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->name} {$this->apellido}"),
        );
    }
}
