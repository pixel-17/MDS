<?php

namespace App\Console\Commands;

use App\Services\GeneradorTurnoMensualService;
use Illuminate\Console\Command;

class GenerarTurnosProximoMes extends Command
{
    protected $signature = 'turnos:generar-proximo-mes';

    protected $description = 'Genera el horario del mes siguiente para trabajadores 728/276 cuya configuración de turno no fue actualizada por Admin/Jefe.';

    public function handle(GeneradorTurnoMensualService $generador): int
    {
        $generador->generarProximoMesParaTodos();

        return self::SUCCESS;
    }
}
