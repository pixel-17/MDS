<?php

namespace App\Models;

use App\Events\PapeletaActualizada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialPapeleta extends Model
{
    const UPDATED_AT = null;

    protected $table = 'historial_papeletas';

    protected $fillable = [
        'papeleta_id',
        'actor_id',
        'actor_tipo',
        'estado_anterior',
        'estado_nuevo',
        'motivo_anterior_id',
        'motivo_nuevo_id',
        'justificacion',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function papeleta(): BelongsTo
    {
        return $this->belongsTo(Papeleta::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function motivoAnterior(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'motivo_anterior_id');
    }

    public function motivoNuevo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'motivo_nuevo_id');
    }

    protected static function booted(): void
    {

        static::created(fn (self $evento) => PapeletaActualizada::notificar($evento->papeleta_id));
    }
}
