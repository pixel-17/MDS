<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

class ObservarRrhhAction
{
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $rrhh, string $comentario): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $rrhh, $comentario) {

            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $rrhh);

            if (! $actual->estado->equals(PendienteRrhh::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
            }

            if ($actual->sinJefatura()) {
                throw new PapeletaException('Esta papeleta no tiene jefe superior que pueda responder una observación: solo puedes aprobarla o rechazarla.');
            }

            $tope = (int) Configuracion::valorDe('TOPE_OBSERVACIONES', 3);

            $actual->contador_observaciones_rrhh++;

            $alcanzoElTope = $actual->contador_observaciones_rrhh >= $tope;

            $actual->transicionarConHistorial(
                $alcanzoElTope ? Rechazada::class : ObservadaPorRrhh::class,
                $rrhh->id,
                'rrhh',
                $comentario,
                function (Papeleta $p) use ($alcanzoElTope, $rrhh, $tope) {
                    if ($alcanzoElTope) {
                        $p->rechazada_por_id = $rrhh->id;
                        $p->motivo_rechazo = "Tope de {$tope} observaciones de RRHH alcanzado.";
                    }
                },
            );

            return $actual;
        });

        if ($papeleta->estado->equals(Rechazada::class)) {
            $this->notificar->rechazada($papeleta);
        } else {
            $this->notificar->observadaPorRrhh($papeleta);
        }

        return $papeleta;
    }
}
