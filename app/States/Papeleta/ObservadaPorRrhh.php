<?php

namespace App\States\Papeleta;

class ObservadaPorRrhh extends PapeletaState
{

    public static ?string $name = 'observada_por_rrhh';

    public function esTerminal(): bool
    {
        return false;
    }
}
