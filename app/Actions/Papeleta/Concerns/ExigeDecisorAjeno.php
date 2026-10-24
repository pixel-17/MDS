<?php

namespace App\Actions\Papeleta\Concerns;

use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;

trait ExigeDecisorAjeno
{
    protected function exigirDecisorAjeno(Papeleta $papeleta, User $decisor): void
    {
        if ((int) $papeleta->trabajador_id === (int) $decisor->id) {
            throw new PapeletaException('No puedes decidir, revisar ni cerrar tu propia papeleta.');
        }
    }
}
