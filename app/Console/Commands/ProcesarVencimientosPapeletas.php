<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\RrhhHorarioService;
use App\Models\Papeleta;
use App\Services\DeterminadorFinDeTurno;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Vencida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcesarVencimientosPapeletas extends Command
{
    protected $signature = 'papeletas:procesar-vencimientos';

    protected $description = 'Vence las papeletas que ya no tienen turno/día vigente y autoriza (con revisión post-hoc) las aprobadas por el jefe que esperan a RRHH cuando RRHH sale de horario.';

    public function __construct(
        private DeterminadorFinDeTurno $finDeTurno,
        private NotificarPapeletaService $notificar,
        private RrhhHorarioService $horarioRrhh,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->vencerPapeletasSinTurnoVigente();

        // Vencida, no autorizada.
        $this->autorizarPendientesDeRrhhFueraDeHorario();

        return self::SUCCESS;
    }

    private function autorizarPendientesDeRrhhFueraDeHorario(): void
    {
        if ($this->horarioRrhh->estaEnHorarioAhora()) {
            return;
        }

        Papeleta::whereState('estado', PendienteRrhh::class)
            ->whereNotNull('jefe_resuelto_at')
            ->conTurnoSinTerminar()
            ->whereHas('trabajador', fn ($q) => $q->queEsJefe())
            ->chunkById(100, function ($lote) {
                foreach ($lote as $candidata) {
                    try {
                        $this->autorizarPorCierreDeRrhh($candidata);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function autorizarPorCierreDeRrhh(Papeleta $candidata): void
    {
        $papeleta = DB::transaction(function () use ($candidata) {
            // Relectura bajo lock: RRHH pudo decidir (aprobar, observar,

            $actual = Papeleta::whereKey($candidata->id)->lockForUpdate()->first();

            if (! $actual
                || ! $actual->estado->equals(PendienteRrhh::class)
                || $this->finDeTurno->yaTermino($actual)) {
                return null;
            }

            $actual->transicionarConHistorial(
                AutorizadaYCorriendo::class,
                null,
                'sistema',
                'RRHH salió de horario con la papeleta ya aprobada por el jefe: se autoriza y queda pendiente de revisión post-hoc.',
                function (Papeleta $p) {
                    $p->autorizado_con_rrhh_fuera_horario = true;
                    $p->hora_salida_real = now();
                    $p->revision_posthoc_estado = 'pendiente';
                },
            );

            return $actual;
        });

        if ($papeleta) {
            $this->notificar->puedeSalir($papeleta);
            $this->notificar->revisionPosthocPendiente($papeleta);
        }
    }

    private function vencerPapeletasSinTurnoVigente(): void
    {
        Papeleta::whereState('estado', $this->estadosNoTerminalesPreAutorizacion())
            ->conTurnoTerminado()
            ->chunkById(100, function ($lote) {
                foreach ($lote as $candidata) {
                    if (! $this->finDeTurno->yaTermino($candidata)) {
                        continue;
                    }

                    try {
                        $this->vencer($candidata);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function vencer(Papeleta $candidata): void
    {
        $papeleta = DB::transaction(function () use ($candidata) {

            $actual = Papeleta::whereKey($candidata->id)->lockForUpdate()->first();

            if (! $actual
                || ! $actual->estado->equals(...$this->estadosNoTerminalesPreAutorizacion())
                || ! $this->finDeTurno->yaTermino($actual)) {
                return null;
            }

            $actual->transicionarConHistorial(
                Vencida::class,
                null,
                'sistema',
                'Vencida automáticamente: fin de turno/día sin decisión.',
                fn (Papeleta $p) => $p->vencida_at = now(),
            );

            return $actual;
        });

        if ($papeleta) {
            $this->notificar->vencida($papeleta);
        }
    }

    /**
     * @return array<class-string>
     */
    private function estadosNoTerminalesPreAutorizacion(): array
    {
        return [
            PendienteJefe::class,
            ObservadaPorJefe::class,
            PendienteRrhh::class,
            ObservadaPorRrhh::class,
        ];
    }
}
