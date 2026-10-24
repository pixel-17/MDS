<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use Illuminate\Support\Facades\DB;

class RevisarSustentoAction
{
    use ExigeDecisorAjeno;

    public function __construct(
        private NotificarPapeletaService $notificar,
        private ReclasificarAParticularAction $reclasificar,
    ) {}

    public function aprobar(Sustento $sustento, User $revisor): Papeleta
    {
        return $this->resolver($sustento, $revisor, 'aprobado');
    }

    public function observar(Sustento $sustento, User $revisor, string $comentario): Papeleta
    {
        return $this->resolver($sustento, $revisor, 'observado', $comentario);
    }

    public function rechazar(Sustento $sustento, User $revisor, string $comentario): Papeleta
    {
        $papeleta = DB::transaction(function () use ($sustento, $revisor, $comentario) {

            $actual = Papeleta::whereKey($sustento->papeleta_id)->lockForUpdate()->firstOrFail();
            /** @var Sustento $sustentoActual */
            $sustentoActual = Sustento::whereKey($sustento->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $revisor);

            if (! $actual->estado->equals(EnJustificacion::class)) {
                throw new PapeletaException('Esta papeleta ya no está en justificación.');
            }

            if ($sustentoActual->estado !== 'presentado') {
                throw new PapeletaException('Este sustento todavía no tiene un archivo presentado para revisar.');
            }

            $sustentoActual->estado = 'rechazado';
            $sustentoActual->revisado_por_id = $revisor->id;
            $sustentoActual->revisado_at = now();
            $sustentoActual->save();

            $this->reclasificar->ejecutar(
                $actual,
                actorId: $revisor->id,
                actorTipo: $revisor->hasRole('rrhh') ? 'rrhh' : 'jefe_inmediato',
                justificacion: $comentario,
            );

            return $actual;
        });

        return $papeleta;
    }

    private function resolver(Sustento $sustento, User $revisor, string $resultado, ?string $comentario = null): Papeleta
    {
        $papeleta = DB::transaction(function () use ($sustento, $revisor, $resultado, $comentario) {

            $actual = Papeleta::whereKey($sustento->papeleta_id)->lockForUpdate()->firstOrFail();
            /** @var Sustento $sustentoActual */
            $sustentoActual = Sustento::whereKey($sustento->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $revisor);

            if (! $actual->estado->equals(EnJustificacion::class)) {
                throw new PapeletaException('Esta papeleta ya no está en justificación.');
            }

            if ($sustentoActual->estado !== 'presentado') {
                throw new PapeletaException('Este sustento todavía no tiene un archivo presentado para revisar.');
            }

            $sustentoActual->estado = $resultado;
            $sustentoActual->revisado_por_id = $revisor->id;
            $sustentoActual->revisado_at = now();
            $sustentoActual->save();

            $estadoAnterior = class_basename($actual->estado);

            if ($resultado === 'aprobado') {
                $actual->transicionarA(Cerrada::class);
                $actual->save();
            } else {
                $sustentoActual->estado = 'pendiente';
                $sustentoActual->save();
            }

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $revisor->id,
                'actor_tipo' => $revisor->hasRole('rrhh') ? 'rrhh' : 'jefe_inmediato',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => $comentario ?? "Sustento {$resultado}.",
            ]);

            return $actual;
        });

        if ($resultado === 'observado') {
            $this->notificar->sustentoObservado($papeleta);
        }

        return $papeleta;
    }
}
