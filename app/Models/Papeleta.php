<?php

namespace App\Models;

use App\Events\PapeletaActualizada;
use App\Exceptions\PapeletaException;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PapeletaState;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

class Papeleta extends Model
{
    use HasStates;

    protected $fillable = [
        'trabajador_id',
        'motivo_id',
        'sede_id',
        'regimen',
        'dia_operativo',
        'fin_turno_at',
        'estado',
        'jefe_inmediato_id',
        'resuelto_por_jefe_id',
        'jefe_resuelto_at',
        'contador_observaciones_jefe',
        'jefe_area_id',
        'resuelto_por_rrhh_id',
        'rrhh_resuelto_at',
        'contador_observaciones_rrhh',
        'autorizado_con_rrhh_fuera_horario',
        'revision_posthoc_estado',
        'revision_posthoc_por_id',
        'revision_posthoc_at',
        'contador_observaciones_posthoc',
        'posthoc_observacion',
        'posthoc_respuesta',
        'posthoc_adjunto_path',
        'posthoc_respondida_at',
        'hora_salida_real',
        'hora_retorno_estimado',
        'descuento_refrigerio_minutos',
        'rechazada_por_id',
        'motivo_rechazo',
        'cancelada_at',
        'vencida_at',
        'causa_finalizacion_sin_retorno',
        'requiere_visto_bueno',
        'regularizacion_fecha_limite',
        'motivo_original_id',
        'justificacion',
        'adjunto_inicial_path',
        'observacion_requiere_adjunto',
        'observacion_respuesta',
        'observacion_adjunto_path',
        'observacion_subsanada_at',
        'reloj_jefe_at',
        'en_revision_por_id',
        'en_revision_desde',
    ];

    protected function casts(): array
    {
        return [
            'estado' => PapeletaState::class,
            'dia_operativo' => 'date',
            'fin_turno_at' => 'datetime',
            'jefe_resuelto_at' => 'datetime',
            'rrhh_resuelto_at' => 'datetime',
            'autorizado_con_rrhh_fuera_horario' => 'boolean',
            'revision_posthoc_at' => 'datetime',
            'posthoc_respondida_at' => 'datetime',
            'hora_salida_real' => 'datetime',
            'hora_retorno_estimado' => 'datetime',
            'cancelada_at' => 'datetime',
            'vencida_at' => 'datetime',
            'requiere_visto_bueno' => 'boolean',
            'regularizacion_fecha_limite' => 'datetime',
            'observacion_requiere_adjunto' => 'boolean',
            'observacion_subsanada_at' => 'datetime',
            'reloj_jefe_at' => 'datetime',
            'en_revision_desde' => 'datetime',
        ];
    }

    protected static function booted(): void
    {

        static::saved(fn (self $papeleta) => PapeletaActualizada::notificar($papeleta->id));
    }

    public function transicionarA(string $destino): void
    {
        if (! $this->estado->canTransitionTo($destino)) {
            throw new PapeletaException(sprintf(
                'Transición de estado no permitida: %s -> %s.',
                class_basename($this->estado),
                class_basename($destino),
            ));
        }

        $this->estado = new $destino($this);
    }

    public function transicionarConHistorial(
        string $destino,
        ?int $actorId,
        string $actorTipo,
        ?string $justificacion = null,
        ?\Closure $cambios = null,
        ?array $camposHistorial = null,
    ): HistorialPapeleta {
        $estadoAnterior = class_basename($this->estado);

        $this->transicionarA($destino);

        if ($cambios) {
            $cambios($this);
        }

        $this->save();

        return HistorialPapeleta::create([
            'papeleta_id' => $this->id,
            'actor_id' => $actorId,
            'actor_tipo' => $actorTipo,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => class_basename($this->estado),
            'justificacion' => $justificacion,
            'motivo_anterior_id' => $camposHistorial['motivo_anterior_id'] ?? null,
            'motivo_nuevo_id' => $camposHistorial['motivo_nuevo_id'] ?? null,
            'metadata' => $camposHistorial['metadata'] ?? null,
        ]);
    }

    public function esAbandono(): bool
    {
        return $this->causa_finalizacion_sin_retorno === 'abandono_no_marcado';
    }

    public const ESTADOS_VIVOS = [
        PendienteJefe::class,
        ObservadaPorJefe::class,
        PendienteRrhh::class,
        ObservadaPorRrhh::class,
        AutorizadaYCorriendo::class,
    ];

    public function scopeVivas(Builder $query): Builder
    {
        return $query->whereState('estado', self::ESTADOS_VIVOS);
    }

    public static function vivaDe(User $trabajador): ?self
    {
        return static::where('trabajador_id', $trabajador->id)->vivas()->latest('id')->first();
    }

    public function mensajeDeBloqueoParaNueva(): string
    {
        if ($this->estado->equals(AutorizadaYCorriendo::class)) {
            return "Ya tienes la papeleta #{$this->id} autorizada y en curso. Finalízala (marca tu retorno) antes de crear otra.";
        }

        return "Ya tienes la papeleta #{$this->id} en trámite. Cancélala o espera su resolución antes de crear otra.";
    }

    public function scopeConTurnoTerminado(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('papeletas.fin_turno_at')
            ->orWhere('papeletas.fin_turno_at', '<', now()));
    }

    public function scopeConTurnoSinTerminar(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('papeletas.fin_turno_at')
            ->orWhere('papeletas.fin_turno_at', '>=', now()));
    }

    public function scopeDeJefeInmediato(Builder $query, User $jefe): Builder
    {
        return $query->where('papeletas.jefe_inmediato_id', $jefe->id);
    }

    public function scopeDelTurnoVigente(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('papeletas.fin_turno_at', '>', now())
            ->orWhere(fn (Builder $q2) => $q2
                ->whereNull('papeletas.fin_turno_at')
                ->whereDate('papeletas.dia_operativo', today())));
    }

    public function scopeDeEquipoDe(Builder $query, User $jefe): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('papeletas.jefe_inmediato_id', $jefe->id)
            ->orWhere('papeletas.jefe_area_id', $jefe->id)
            ->orWhereIn('papeletas.trabajador_id', User::equipoDe($jefe)->select('users.id')));
    }

    public function tieneComoJefeInmediatoA(User $user): bool
    {
        return $this->jefe_inmediato_id !== null && (int) $this->jefe_inmediato_id === (int) $user->id;
    }

    public function sinJefatura(): bool
    {
        return $this->jefe_inmediato_id === null && $this->jefe_area_id === null;
    }

    public function puedeResponderPosthoc(User $user): bool
    {
        return $this->autorizado_con_rrhh_fuera_horario
            && $this->revision_posthoc_estado === 'observada'
            && $this->resuelto_por_jefe_id !== null
            && (int) $this->resuelto_por_jefe_id === (int) $user->id
            && (int) $this->trabajador_id !== (int) $user->id;
    }

    public function etiquetaPosthoc(): string
    {
        return match ($this->revision_posthoc_estado) {
            'observada' => 'Observada — espera respuesta del jefe',
            'respondida' => 'Respondida por el jefe — pendiente de RRHH',
            'observada_firme' => 'Observada (reparo definitivo) — pasó a Particular',
            'no_aprobada' => 'No aprobada — pasó a Particular',
            'aprobada' => 'Aprobada',
            'no_aplica' => 'No aplica',
            default => 'Pendiente',
        };
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trabajador_id');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class);
    }

    public function motivoOriginal(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'motivo_original_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function jefeInmediato(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_id');
    }

    public function resueltoPorJefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_jefe_id');
    }

    public function jefeArea(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_id');
    }

    public function resueltoPorRrhh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_rrhh_id');
    }

    public function revisionPosthocPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revision_posthoc_por_id');
    }

    public function rechazadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazada_por_id');
    }

    public function enRevisionPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'en_revision_por_id');
    }

    public function retorno(): HasOne
    {
        return $this->hasOne(Retorno::class);
    }

    public function sustentos(): HasMany
    {
        return $this->hasMany(Sustento::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialPapeleta::class);
    }
}
