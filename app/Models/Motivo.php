<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
  * @property int $id
  * @property string $codigo
  * @property string $nombre
  * @property 'no'|'opcional'|'flexible'|'obligatorio' $adjunto
  * @property bool $suma_descuento
  * @property bool $requiere_sustento_en_retorno
  * @property int|null $plazo_justificacion_horas_habiles
  * @property bool $es_destino_reclasificacion
  * @property bool $activo
  * @property-read Collection<int, Papeleta> $papeletas
  */
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

    /**
      * @return 'justificar'|'descuenta'|'libre'
      */
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
        return (int) $this->plazo_justificacion_horas_habiles;
    }

    /**
      * @return HasMany<Papeleta, $this>
      */
    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class);
    }
}
