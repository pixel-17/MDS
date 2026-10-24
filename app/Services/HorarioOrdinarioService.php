<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Carbon;

class HorarioOrdinarioService
{
    public function __construct(private CalculadorDiasHabiles $diasHabiles) {}

    public const HORA_INICIO_DEFECTO = '07:45';

    public const HORA_FIN_DEFECTO = '16:15';

    public function estaDentroDeVentana(?Carbon $momento = null): bool
    {
        $momento ??= now();

        if (! $this->diasHabiles->esHabil($momento)) {
            return false;
        }

        [$horaInicio] = $this->ventanaDeHoy();

        $inicio = $momento->copy()->setTimeFromTimeString($horaInicio)->startOfMinute();

        return $momento->greaterThanOrEqualTo($inicio) && $momento->lessThanOrEqualTo($this->finDelDia($momento));
    }

    public function finDelDia(Carbon $fecha): Carbon
    {
        [, $horaFin] = $this->ventanaDeHoy();

        return $fecha->copy()->setTimeFromTimeString($horaFin)->endOfMinute();
    }

    public function yaTerminoElDia(Carbon $fecha): bool
    {
        return now()->greaterThan($this->finDelDia($fecha));
    }

    /**
     * @return array{0: string, 1: string} [hora_inicio, hora_fin] en formato H:i
     */
    private function ventanaDeHoy(): array
    {
        return [
            (string) Configuracion::valorDe('HORARIO_ORDINARIO_HORA_INICIO', self::HORA_INICIO_DEFECTO),
            (string) Configuracion::valorDe('HORARIO_ORDINARIO_HORA_FIN', self::HORA_FIN_DEFECTO),
        ];
    }
}
