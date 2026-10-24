<?php

namespace App\States\Papeleta;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PapeletaState extends State
{
    abstract public function esTerminal(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendienteJefe::class)
            ->allowTransition(PendienteJefe::class, ObservadaPorJefe::class)
            ->allowTransition(PendienteJefe::class, Rechazada::class)
            ->allowTransition(PendienteJefe::class, PendienteRrhh::class)
            ->allowTransition(PendienteJefe::class, AutorizadaYCorriendo::class)
            ->allowTransition(PendienteJefe::class, Vencida::class)
            ->allowTransition(PendienteJefe::class, Cancelada::class)

            ->allowTransition(ObservadaPorJefe::class, PendienteJefe::class)
            ->allowTransition(ObservadaPorJefe::class, Rechazada::class)

            ->allowTransition(ObservadaPorJefe::class, PendienteRrhh::class)
            ->allowTransition(ObservadaPorJefe::class, AutorizadaYCorriendo::class)
            ->allowTransition(ObservadaPorJefe::class, Vencida::class)
            ->allowTransition(ObservadaPorJefe::class, Cancelada::class)

            ->allowTransition(PendienteRrhh::class, ObservadaPorRrhh::class)
            ->allowTransition(PendienteRrhh::class, Rechazada::class)
            ->allowTransition(PendienteRrhh::class, AutorizadaYCorriendo::class)
            ->allowTransition(PendienteRrhh::class, Vencida::class)
            ->allowTransition(PendienteRrhh::class, Cancelada::class)

            ->allowTransition(ObservadaPorRrhh::class, PendienteJefe::class)
            ->allowTransition(ObservadaPorRrhh::class, Vencida::class)
            ->allowTransition(ObservadaPorRrhh::class, Cancelada::class)

            ->allowTransition(AutorizadaYCorriendo::class, Cerrada::class)
            ->allowTransition(AutorizadaYCorriendo::class, Finalizada::class)
            ->allowTransition(AutorizadaYCorriendo::class, EnJustificacion::class)

            ->allowTransition(Cerrada::class, Finalizada::class)

            ->allowTransition(EnJustificacion::class, Cerrada::class)
            ->allowTransition(EnJustificacion::class, Finalizada::class);
    }
}
