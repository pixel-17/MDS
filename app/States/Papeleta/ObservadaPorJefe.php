<?php

namespace App\States\Papeleta;

class ObservadaPorJefe extends PapeletaState
{

    public static ?string $name = 'observada_por_jefe';

    public function esTerminal(): bool
    {
        return false;
    }
}
