<?php

namespace App\Models;

use App\Events\HorarioActualizado;
use App\Support\TurnoColores;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Turno extends Model
{
    protected static function booted(): void
    {

        //

        // consultas masivas (ProgramacionTurnoService, GeneradorTurno-

        static::saved(function (self $turno) {
            $antes = $turno->wasChanged('user_id') ? (int) $turno->getOriginal('user_id') : null;

            HorarioActualizado::notificar(...array_filter([$turno->user_id, $antes]));
        });

        static::deleted(fn (self $turno) => HorarioActualizado::notificar($turno->user_id));
    }

    protected $fillable = [
        'user_id',
        'sede_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'es_descanso',
        'turno',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'es_descanso' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function cubre(Carbon $momento): bool
    {
        $limites = $this->limites();

        if ($limites === null) {
            return false;
        }

        [$inicio, $fin] = $limites;

        return $momento->greaterThanOrEqualTo($inicio) && $momento->lessThanOrEqualTo($fin);
    }

    public function finReal(): ?Carbon
    {
        return $this->limites()[1] ?? null;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null [inicio, fin] absolutos
     */
    private function limites(): ?array
    {
        if ($this->es_descanso || ! $this->hora_inicio || ! $this->hora_fin) {
            return null;
        }

        $inicio = $this->fecha->copy()->setTimeFromTimeString($this->hora_inicio);
        $fin = $this->fecha->copy()->setTimeFromTimeString($this->hora_fin);

        if ($fin->lessThanOrEqualTo($inicio)) {
            $fin->addDay();
        }

        return [$inicio, $fin];
    }

    public static function vigenteParaUsuario(int $userId, ?Carbon $momento = null): ?self
    {
        $momento ??= now();

        return static::where('user_id', $userId)

            ->where(fn ($q) => $q
                ->whereDate('fecha', $momento->toDateString())
                ->orWhereDate('fecha', $momento->copy()->subDay()->toDateString()))
            ->get()
            ->first(fn (self $turno) => $turno->cubre($momento));
    }

    public function codigo(): string
    {
        if ($this->es_descanso || ! $this->hora_inicio) {
            return 'DESCANSO';
        }

        if ($this->turno) {
            return $this->turno;
        }

        return match ((string) $this->hora_inicio) {
            (string) Configuracion::valorDe('TURNO_MANANA_HORA_INICIO', '06:00') => 'MANANA',
            (string) Configuracion::valorDe('TURNO_TARDE_HORA_INICIO', '14:00') => 'TARDE',
            (string) Configuracion::valorDe('TURNO_NOCHE_HORA_INICIO', '22:00') => 'NOCHE',
            default => 'DIA',
        };
    }

    public function etiqueta(): string
    {
        return match ($this->codigo()) {
            'DESCANSO' => 'D',
            'MANANA' => 'M',
            'TARDE' => 'T',
            'NOCHE' => 'N',
            default => 'DIA',
        };
    }

    public function nombreTurno(): string
    {
        return match ($this->etiqueta()) {
            'M' => 'Mañana',
            'T' => 'Tarde',
            'N' => 'Noche',
            'D' => 'Descanso',
            default => 'Horario ordinario',
        };
    }

    public function claseColor(): string
    {
        return TurnoColores::para($this->etiqueta());
    }
}
