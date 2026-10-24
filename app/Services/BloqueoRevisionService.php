<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use Carbon\Carbon;

class BloqueoRevisionService
{

    private const MINUTOS_RENOVACION = 2;

    public function marcar(Papeleta $papeleta, User $usuario): bool
    {

        if (
            $papeleta->en_revision_por_id &&
            $papeleta->en_revision_por_id !== $usuario->id
        ) {
            return false;
        }

        if (
            $papeleta->en_revision_por_id === $usuario->id
            && $papeleta->en_revision_desde
            && $papeleta->en_revision_desde->gt(Carbon::now()->subMinutes(self::MINUTOS_RENOVACION))
        ) {
            return true;
        }

        $papeleta->update([
            'en_revision_por_id' => $usuario->id,
            'en_revision_desde' => Carbon::now(),
        ]);

        return true;
    }

    public function liberar(Papeleta $papeleta, User $usuario): void
    {
        if ($papeleta->en_revision_por_id === $usuario->id) {
            $papeleta->update([
                'en_revision_por_id' => null,
                'en_revision_desde' => null,
            ]);
        }
    }

    public function nombreRevisor(Papeleta $papeleta): ?string
    {
        return $papeleta->enRevisionPor?->name;
    }
}
