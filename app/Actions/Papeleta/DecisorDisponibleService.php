<?php

namespace App\Actions\Papeleta;

use App\Models\Turno;
use App\Models\User;
use App\Services\HorarioOrdinarioService;
use Illuminate\Support\Carbon;

class DecisorDisponibleService
{
    public function __construct(
        private HorarioOrdinarioService $horarioOrdinario,
    ) {}

    public function estaDisponibleAhora(?User $decisor): bool
    {
        return $this->estaDisponible($decisor, Carbon::now());
    }

    public function estaDisponible(?User $decisor, Carbon $momento): bool
    {
        if (! $decisor) {
            return false;
        }

        if (! $decisor->activo) {
            return false;
        }

        return match ($decisor->regimen) {
            '728' => ! $this->estaDeDescanso($decisor, $momento),

            '276' => $this->horarioOrdinario->estaDentroDeVentana($momento),
            default => false,
        };
    }

    private function estaDeDescanso(User $decisor, Carbon $momento): bool
    {
        if (Turno::vigenteParaUsuario($decisor->id, $momento) !== null) {
            return false;
        }

        return Turno::where('user_id', $decisor->id)
            ->whereDate('fecha', $momento->toDateString())
            ->where('es_descanso', true)
            ->exists();
    }
}
