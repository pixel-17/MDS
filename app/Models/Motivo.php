<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Motivo extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'adjunto',
        'suma_descuento',
        'requiere_sustento_en_retorno',
        'plazo_justificacion_horas_habiles',
        'es_destino_reclasificacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'suma_descuento' => 'boolean',
            'requiere_sustento_en_retorno' => 'boolean',
            'plazo_justificacion_horas_habiles' => 'integer',
            'es_destino_reclasificacion' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function consecuenciaAlTerminar(): string
    {
        return match (true) {
            (bool) $this->requiere_sustento_en_retorno => 'justificar',
            (bool) $this->suma_descuento => 'descuenta',
            default => 'libre',
        };
    }

    public function plazoJustificacionHorasHabiles(): int
    {
        return $this->plazo_justificacion_horas_habiles
            ?? (int) Configuracion::valorDe('SUSTENTO_HORAS_HABILES', 48);
    }

    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class);
    }
}
