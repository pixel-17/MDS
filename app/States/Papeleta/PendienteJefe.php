<?php

namespace App\States\Papeleta;

class PendienteJefe extends PapeletaState
{

    public static ?string $name = 'pendiente_jefe';

    public function esTerminal(): bool
    {
        return false;
    }
}
