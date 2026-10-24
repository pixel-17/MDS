<?php

namespace App\States\Papeleta;

class PendienteRrhh extends PapeletaState
{

    public static ?string $name = 'pendiente_rrhh';

    public function esTerminal(): bool
    {
        return false;
    }
}
