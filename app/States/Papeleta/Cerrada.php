<?php

namespace App\States\Papeleta;

class Cerrada extends PapeletaState
{

    public static ?string $name = 'cerrada';

    public function esTerminal(): bool
    {
        return true;
    }
}
