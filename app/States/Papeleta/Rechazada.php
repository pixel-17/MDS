<?php

namespace App\States\Papeleta;

class Rechazada extends PapeletaState
{

    public static ?string $name = 'rechazada';

    public function esTerminal(): bool
    {
        return true;
    }
}
