<?php

namespace App\Services;

use App\Models\Configuracion;
use Carbon\Carbon;

class CalculadorDiasHabiles
{
    public function esHabil(Carbon $fecha): bool
    {
        return $this->esDiaLaborable($fecha);
    }

    public function esDiaLaborable(Carbon $fecha): bool
    {
        $dias = array_map(
            'trim',
            explode(',', (string) Configuracion::valorDe('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5'))
        );

        return in_array((string) $fecha->isoWeekday(), $dias, true);
    }

    public function agregarDiasHabiles(Carbon $desde, int $dias): Carbon
    {
        $fecha = $desde->copy();

        while ($dias > 0) {
            $fecha->addDay();

            if ($this->esHabil($fecha)) {
                $dias--;
            }
        }

        return $fecha;
    }

    public function agregarHorasHabiles(Carbon $desde, int $horas): Carbon
    {
        $fecha = $desde->copy();
        $horasRestantes = $horas;

        while ($horasRestantes > 0) {
            $fecha->addHour();

            if ($this->esHabil($fecha)) {
                $horasRestantes--;
            }
        }

        return $fecha;
    }
}
