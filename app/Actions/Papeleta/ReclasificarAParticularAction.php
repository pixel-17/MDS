<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use Illuminate\Support\Facades\DB;

class ReclasificarAParticularAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(
        Papeleta $papeleta,
        ?int $actorId,
        string $actorTipo,
        string $justificacion,
    ): Papeleta {
        $motivoParticular = Motivo::where('es_destino_reclasificacion', true)->first();

        if (! $motivoParticular) {
            throw new PapeletaException('No hay un motivo configurado como destino de reclasificación (es_destino_reclasificacion).');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $motivoParticular, $actorId, $actorTipo, $justificacion) {

            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->equals(EnJustificacion::class)) {
                throw new PapeletaException('Esta papeleta no está en un estado que se pueda reclasificar a Particular.');
            }

            $motivoAnteriorId = $actual->motivo_id;

            $actual->motivo_original_id = $motivoAnteriorId;
            $actual->motivo_id = $motivoParticular->id;

            $actual->transicionarConHistorial(
                Finalizada::class,
                $actorId,
                $actorTipo,
                $justificacion,
                function (Papeleta $p) {
                    $p->causa_finalizacion_sin_retorno ??= 'salud_no_justificada';
                    $p->requiere_visto_bueno = false;
                },
                camposHistorial: [
                    'motivo_anterior_id' => $motivoAnteriorId,
                    'motivo_nuevo_id' => $motivoParticular->id,
                ],
            );

            return $actual;
        });

        $this->notificar->reclasificadaAParticular($papeleta);

        return $papeleta;
    }
}
