<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Support\Facades\DB;

class CancelarPapeletaAction
{
    public function __construct(private RrhhHorarioService $horarioRrhh) {}

    public function ejecutar(Papeleta $papeleta, User $trabajador): Papeleta
    {
        if ($papeleta->trabajador_id !== $trabajador->id) {
            throw new PapeletaException('No puedes cancelar una papeleta que no es tuya.');
        }

        return DB::transaction(function () use ($papeleta, $trabajador) {

            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $enFaseJefe = $actual->estado->equals(PendienteJefe::class, ObservadaPorJefe::class);
            $enFaseRrhh = $actual->estado->equals(PendienteRrhh::class, ObservadaPorRrhh::class);

            if (! $enFaseJefe && ! $enFaseRrhh) {
                throw new PapeletaException('Esta papeleta ya fue autorizada o resuelta y no se puede cancelar.');
            }

            if ($enFaseRrhh && ! $this->horarioRrhh->estaEnHorarioAhora()) {
                throw new PapeletaException('RRHH está fuera de horario: no se puede cancelar la papeleta mientras espera a RRHH.');
            }

            $actual->transicionarConHistorial(
                Cancelada::class,
                $trabajador->id,
                'trabajador',
                cambios: fn (Papeleta $p) => $p->cancelada_at = now(),
            );

            return $actual;
        });
    }
}
