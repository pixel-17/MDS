<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read string $nombre_completo
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
     *  @var list<string>
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
     *  @var list<string>
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
     *  @var list<string>
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

    public function jefeInmediato(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_id');
    }

    public function jefeArea(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_id');
    }

    public function unidadesQueEncabeza(): HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'jefe_id');
    }

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

    private ?string $tipoUsuarioMemo = null;

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

    public function scopeQueEsJefe(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereHas('unidadesQueEncabeza')
            ->orWhereIn('users.id', User::query()
                ->select('jefe_inmediato_id')
                ->whereNotNull('jefe_inmediato_id')));
    }

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

    public function equipo(): Collection
    {
        return User::equipoDe($this)->orderBy('name')->get();
    }

    public function trabajadoresComoJefeInmediato(): Collection
    {
        return $this->equipo();
    }

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

    public function jefesInmediatos(): Collection
    {
        return $this->jefe_inmediato_id === null
            ? collect()
            : User::whereKey($this->jefe_inmediato_id)->get();
    }

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

    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class, 'trabajador_id');
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->name} {$this->apellido}"),
        );
    }
}
