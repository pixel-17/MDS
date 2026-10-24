<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\Sustento;
use Carbon\Carbon;

class AbrirJustificacion
{
    public function __construct(private CalculadorDiasHabiles $diasHabiles) {}

    public function para(Papeleta $papeleta, Carbon $desde): Sustento
    {
        $yaAdjunto = filled($papeleta->adjunto_inicial_path);

        return Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => $this->diasHabiles->agregarHorasHabiles(
                $desde->copy(),
                $papeleta->motivo->plazoJustificacionHorasHabiles(),
            ),
            'archivo_path' => $yaAdjunto ? $papeleta->adjunto_inicial_path : null,
            'estado' => $yaAdjunto ? 'presentado' : 'pendiente',
            'presentado_at' => $yaAdjunto ? now() : null,
        ]);
    }
}
