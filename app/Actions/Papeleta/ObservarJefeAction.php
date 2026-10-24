<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

class ObservarJefeAction
{
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $jefe, string $comentario, string $actorTipo = 'jefe_inmediato', bool $requiereAdjunto = false): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $jefe, $comentario, $actorTipo, $requiereAdjunto) {

            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $jefe);

            if (! $actual->estado->equals(PendienteJefe::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión del jefe.');
            }

            $tope = (int) Configuracion::valorDe('TOPE_OBSERVACIONES', 3);

            $actual->contador_observaciones_jefe++;

            $alcanzoElTope = $actual->contador_observaciones_jefe >= $tope;

            $actual->transicionarConHistorial(
                $alcanzoElTope ? Rechazada::class : ObservadaPorJefe::class,
                $jefe->id,
                $actorTipo,
                $comentario,
                function (Papeleta $p) use ($alcanzoElTope, $jefe, $tope, $requiereAdjunto) {
                    if ($alcanzoElTope) {
                        $p->rechazada_por_id = $jefe->id;
                        $p->motivo_rechazo = "Tope de {$tope} observaciones alcanzado.";

                        return;
                    }

                    $p->observacion_requiere_adjunto = $requiereAdjunto;
                    $p->observacion_respuesta = null;

                    $p->observacion_adjunto_path = null;
                    $p->observacion_subsanada_at = null;
                },
            );

            return $actual;
        });

        if ($papeleta->estado->equals(Rechazada::class)) {
            $this->notificar->rechazada($papeleta);
        } else {
            $this->notificar->observadaPorJefe($papeleta);
        }

        return $papeleta;
    }
}
