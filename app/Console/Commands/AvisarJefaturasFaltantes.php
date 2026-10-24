<?php

namespace App\Console\Commands;

use App\Services\AlertaJefaturaService;
use Illuminate\Console\Command;

class AvisarJefaturasFaltantes extends Command
{
    protected $signature = 'jefaturas:avisar-faltantes';

    protected $description = 'Avisa a admin y Jefe de Área de las unidades que se quedaron sin jefe activo.';

    public function handle(AlertaJefaturaService $alerta): int
    {
        $avisos = $alerta->avisarFaltantesEnTodoElOrganigrama();

        $this->info("Avisos de jefatura faltante disparados: {$avisos}.");

        return self::SUCCESS;
    }
}
