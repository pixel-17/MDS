<?php

namespace App\Actions\Papeleta;

use App\Models\User;
use App\Services\HorarioOrdinarioService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class RrhhHorarioService
{
    public function __construct(
        private HorarioOrdinarioService $horarioOrdinario,
    ) {}

    public function estaEnHorarioAhora(): bool
    {
        return $this->estaEnHorario(now());
    }

    public function estaEnHorario(CarbonInterface $momento): bool
    {

        $momento = Carbon::instance($momento);

        if (! $this->hayPersonalRrhhDesignado()) {
            return false;
        }

        return $this->horarioOrdinario->estaDentroDeVentana($momento);
    }

    protected function hayPersonalRrhhDesignado(): bool
    {
        return User::role('rrhh')->where('activo', true)->exists();
    }
}
