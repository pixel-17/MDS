<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaTurnoMensual extends Model
{
    protected $table = 'cargas_turno_mensuales';

    protected $fillable = [
        'user_id',
        'anio',
        'mes',
        'origen',
        'configuracion_turno_id',
        'generado_por_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function configuracionTurno(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionTurno::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }
}
