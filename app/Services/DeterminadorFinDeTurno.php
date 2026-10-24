<?php

namespace App\Services;

use App\Models\Papeleta;

class DeterminadorFinDeTurno
{
    public function __construct(private HorarioOrdinarioService $horarioOrdinario) {}

    public function yaTermino(Papeleta $papeleta): bool
    {
        if ($papeleta->fin_turno_at !== null) {
            return now()->greaterThan($papeleta->fin_turno_at);
        }

        return $this->yaTerminoLegado($papeleta);
    }

    private function yaTerminoLegado(Papeleta $papeleta): bool
    {
        if ($papeleta->regimen === '728') {

            return now()->toDateString() > $papeleta->dia_operativo->toDateString();
        }

        return $this->horarioOrdinario->yaTerminoElDia($papeleta->dia_operativo);
    }
}
