<?php

namespace App\States\Papeleta;

class Vencida extends PapeletaState
{

    public static ?string $name = 'vencida';

    public function esTerminal(): bool
    {
        return true;
    }
}
