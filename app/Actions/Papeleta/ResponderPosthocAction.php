<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use Illuminate\Support\Facades\DB;

class ResponderPosthocAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $jefe, string $respuesta, ?string $adjuntoPath = null): Papeleta
    {
        $respuesta = trim($respuesta);

        if ($respuesta === '') {
            throw new PapeletaException('Debes escribir tu respuesta a la observación de RRHH.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $jefe, $respuesta, $adjuntoPath) {

            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if ($actual->revision_posthoc_estado !== 'observada') {
                throw new PapeletaException('Esta papeleta no tiene una observación post-hoc pendiente de respuesta.');
            }

            if ($actual->resuelto_por_jefe_id === null
                || (int) $actual->resuelto_por_jefe_id !== (int) $jefe->id) {
                throw new PapeletaException('Solo el jefe que autorizó esta papeleta puede responder la observación de RRHH.');
            }

            $actual->revision_posthoc_estado = 'respondida';
            $actual->posthoc_respuesta = $respuesta;
            $actual->posthoc_adjunto_path = $adjuntoPath;
            $actual->posthoc_respondida_at = now();
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $jefe->id,
                'actor_tipo' => 'jefe_inmediato',
                'estado_anterior' => class_basename($actual->estado),
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => 'Respuesta a la observación post-hoc de RRHH: '.$respuesta,
            ]);

            return $actual;
        });

        $this->notificar->posthocRespondida($papeleta);

        return $papeleta;
    }
}
