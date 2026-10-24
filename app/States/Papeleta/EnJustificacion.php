<?php

namespace App\States\Papeleta;

class EnJustificacion extends PapeletaState
{

    public static ?string $name = 'en_justificacion';

    public function esTerminal(): bool
    {
        return false;
    }
}
