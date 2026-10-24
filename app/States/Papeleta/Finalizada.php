<?php

namespace App\States\Papeleta;

class Finalizada extends PapeletaState
{

    public static ?string $name = 'finalizada';

    public function esTerminal(): bool
    {
        return true;
    }
}
