<?php

namespace App\States\Papeleta;

class AutorizadaYCorriendo extends PapeletaState
{

    public static ?string $name = 'autorizada_y_corriendo';

    public function esTerminal(): bool
    {
        return false;
    }
}
