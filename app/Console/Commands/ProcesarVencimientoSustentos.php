<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\ReclasificarAParticularAction;
use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\EnJustificacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcesarVencimientoSustentos extends Command
{
    protected $signature = 'papeletas:procesar-vencimiento-sustentos';

    protected $description = 'Pasa a Finalizada (Particular) las justificaciones vencidas sin presentar; marca visto bueno pendiente si hay archivo sin revisar.';

    public function handle(ReclasificarAParticularAction $reclasificar, NotificarPapeletaService $notificar): int
    {

        Sustento::where('estado', 'pendiente')
            ->where('fecha_limite', '<=', now())
            ->whereHas('papeleta', fn ($q) => $q->whereState('estado', EnJustificacion::class))
            ->with('papeleta')
            ->chunkById(100, function ($lote) use ($reclasificar) {
                foreach ($lote as $sustento) {
                    try {
                        DB::transaction(function () use ($sustento, $reclasificar) {

                            $papeleta = Papeleta::whereKey($sustento->papeleta_id)->lockForUpdate()->first();
                            $actual = Sustento::whereKey($sustento->id)->lockForUpdate()->first();

                            if (! $papeleta
                                || ! $actual
                                || $actual->estado !== 'pendiente'
                                || $actual->fecha_limite->isFuture()
                                || ! $papeleta->estado->equals(EnJustificacion::class)) {
                                return;
                            }

                            $actual->estado = 'vencido';
                            $actual->save();

                            $reclasificar->ejecutar(
                                $papeleta,
                                actorId: null,
                                actorTipo: 'sistema',
                                justificacion: 'Reclasificado automáticamente: la justificación venció sin presentarse dentro del plazo del motivo.',
                            );
                        });
                    } catch (PapeletaException) {

                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        Sustento::where('estado', 'presentado')
            ->where('fecha_limite', '<=', now())
            ->whereHas('papeleta', fn ($q) => $q->where('requiere_visto_bueno', false)
                ->whereState('estado', EnJustificacion::class))
            ->with('papeleta')
            ->chunkById(100, function ($lote) use ($notificar) {
                foreach ($lote as $sustento) {
                    try {
                        $papeleta = DB::transaction(function () use ($sustento) {
                            $papeleta = Papeleta::whereKey($sustento->papeleta_id)->lockForUpdate()->first();

                            if (! $papeleta
                                || $papeleta->requiere_visto_bueno
                                || ! $papeleta->estado->equals(EnJustificacion::class)) {
                                return null;
                            }

                            $papeleta->requiere_visto_bueno = true;
                            $papeleta->save();

                            HistorialPapeleta::create([
                                'papeleta_id' => $papeleta->id,
                                'actor_id' => null,
                                'actor_tipo' => 'sistema',
                                'estado_anterior' => class_basename($papeleta->estado),
                                'estado_nuevo' => class_basename($papeleta->estado),
                                'justificacion' => 'Sustento presentado sin revisar: venció el plazo de 48h hábiles sin decisión humana.',
                            ]);

                            return $papeleta;
                        });

                        if ($papeleta) {
                            $notificar->sustentoSinRevisar($papeleta);
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        return self::SUCCESS;
    }
}
