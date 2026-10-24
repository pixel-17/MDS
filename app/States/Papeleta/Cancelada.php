<?php

namespace App\States\Papeleta;

class Cancelada extends PapeletaState
{

    public static ?string $name = 'cancelada';

    public function esTerminal(): bool
    {
        return true;
    }
}
