<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionTurno extends Model
{
    protected $table = 'configuraciones_turno';

    protected $fillable = [
        'user_id',
        'turno',
        'fecha_ancla',
        'dias_trabajo',
        'dias_descanso',
        'actualizado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ancla' => 'date',
        ];
    }

    public const TURNOS_728 = ['MANANA', 'TARDE', 'NOCHE'];

    public const TURNO_276 = 'DIA';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    public static function turnosValidosPara(User $trabajador): array
    {
        return $trabajador->regimen === '728' ? self::TURNOS_728 : [self::TURNO_276];
    }

    public static function etiquetaDeTurno(?string $turno): string
    {
        return match ($turno) {
            'MANANA' => 'Mañana',
            'TARDE' => 'Tarde',
            'NOCHE' => 'Noche',
            self::TURNO_276 => 'Día',
            default => (string) $turno,
        };
    }
}
