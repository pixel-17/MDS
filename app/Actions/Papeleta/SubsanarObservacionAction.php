<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Support\Facades\DB;

class SubsanarObservacionAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $trabajador, string $respuesta, ?string $adjuntoPath = null): Papeleta
    {
        if ((int) $papeleta->trabajador_id !== (int) $trabajador->id) {
            throw new PapeletaException('No puedes responder una papeleta que no es tuya.');
        }

        $respuesta = trim($respuesta);

        if ($respuesta === '') {
            throw new PapeletaException('Debes escribir tu respuesta a la observación.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $trabajador, $respuesta, $adjuntoPath) {

            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->equals(ObservadaPorJefe::class)) {
                throw new PapeletaException('Esta papeleta ya no tiene una observación pendiente de respuesta.');
            }

            if ($actual->observacion_requiere_adjunto && ! $adjuntoPath) {
                throw new PapeletaException('Tu jefe pidió que adjuntes un archivo junto con tu respuesta.');
            }

            $actual->transicionarConHistorial(
                PendienteJefe::class,
                $trabajador->id,
                'trabajador',
                $respuesta,
                function (Papeleta $p) use ($respuesta, $adjuntoPath) {
                    $p->observacion_respuesta = $respuesta;
                    $p->observacion_adjunto_path = $adjuntoPath;
                    $p->observacion_subsanada_at = now();
                    $p->jefe_resuelto_at = null;
                    $p->reloj_jefe_at = now();
                },
            );

            return $actual;
        });

        $this->notificar->observacionRespondida($papeleta);

        return $papeleta;
    }
}
