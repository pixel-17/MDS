<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\MarcarRetornoAction;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Retorno;
use App\Services\AbrirJustificacion;
use App\Services\DeterminadorFinDeTurno;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcesarAbandonoNoMarcado extends Command
{
    protected $signature = 'papeletas:procesar-abandono-no-marcado';

    protected $description = 'Cierra con causa de abandono las papeletas en curso cuyo turno/día terminó sin que el trabajador marcara retorno.';

    public function handle(DeterminadorFinDeTurno $finDeTurno, NotificarPapeletaService $notificar, MarcarRetornoAction $marcarRetorno): int
    {
        Papeleta::whereState('estado', AutorizadaYCorriendo::class)
            ->conTurnoTerminado()
            ->whereDoesntHave('retorno')
            ->chunkById(100, function ($lote) use ($finDeTurno, $notificar, $marcarRetorno) {
                foreach ($lote as $candidata) {
                    if (! $finDeTurno->yaTermino($candidata)) {
                        continue;
                    }

                    try {
                        $papeleta = DB::transaction(function () use ($candidata, $finDeTurno, $marcarRetorno) {

                            $actual = Papeleta::whereKey($candidata->id)->lockForUpdate()->first();

                            if (! $actual
                                || ! $actual->estado->equals(AutorizadaYCorriendo::class)
                                || $actual->retorno()->exists()
                                || ! $finDeTurno->yaTermino($actual)) {
                                return null;
                            }

                            $estadoAnterior = class_basename($actual->estado);

                            if (! $actual->retorno) {
                                $horaFin = $actual->fin_turno_at ?? now();

                                Retorno::create([
                                    'papeleta_id' => $actual->id,
                                    'hora_servidor' => $horaFin,
                                    'generado_por_abandono' => true,
                                ]);

                                // almuerzo.
                                $actual->descuento_refrigerio_minutos = $marcarRetorno->calcularDescuentoRefrigerio($actual, $horaFin);
                            }

                            $consecuencia = $actual->motivo->consecuenciaAlTerminar();

                            $actual->transicionarA(match ($consecuencia) {
                                'justificar' => EnJustificacion::class,
                                'descuenta' => Finalizada::class,
                                default => Cerrada::class,
                            });
                            $actual->causa_finalizacion_sin_retorno = 'abandono_no_marcado';
                            $actual->requiere_visto_bueno = false;
                            $actual->regularizacion_fecha_limite = null;
                            $actual->save();

                            if ($consecuencia === 'justificar') {
                                app(AbrirJustificacion::class)->para($actual, now());
                            }

                            HistorialPapeleta::create([
                                'papeleta_id' => $actual->id,
                                'actor_id' => null,
                                'actor_tipo' => 'sistema',
                                'estado_anterior' => $estadoAnterior,
                                'estado_nuevo' => class_basename($actual->estado),
                                'justificacion' => $consecuencia === 'justificar'
                                    ? 'Abandono no marcado: turno/día terminó sin registro de retorno. Queda en justificación dentro del plazo del motivo. Notificado a jefe y RRHH.'
                                    : 'Abandono no marcado: turno/día terminó sin registro de retorno. Papeleta cerrada. Notificado a jefe y RRHH.',
                            ]);

                            return $actual;
                        });

                        if ($papeleta) {
                            $notificar->abandonoNoMarcado($papeleta);
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        return self::SUCCESS;
    }
}
