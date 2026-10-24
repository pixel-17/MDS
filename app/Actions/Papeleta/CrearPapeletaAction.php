<?php

declare(strict_types=1);

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\Turno;
use App\Models\User;
use App\Services\HorarioOrdinarioService;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CrearPapeletaAction
{
    public function __construct(
        private NotificarPapeletaService $notificar,
        private RrhhHorarioService $horarioRrhh,
    ) {}

    public function ejecutar(User $trabajador, Motivo $motivo, array $datos): Papeleta
    {
        $this->validarMotivoYRegimen($trabajador, $motivo, $datos);
        $this->exigirSinPapeletaViva($trabajador);

        $turno = $this->resolverTurnoActivo($trabajador);
        $finTurno = $this->resolverFinDeTurno($trabajador, $turno);

        $jefatura = $this->resolverJefatura($trabajador);

        $estadoInicial = $this->determinarEstadoInicial($jefatura);
        $autorizaSistema = $estadoInicial === AutorizadaYCorriendo::class;

        $papeleta = DB::transaction(fn () => $this->crearPapeletaConHistorial(
            $trabajador,
            $motivo,
            $datos,
            $turno,
            $finTurno,
            $jefatura,
            $estadoInicial,
            $autorizaSistema,
        ));

        // confirmada en BD.
        $this->notificarCreacion($papeleta, $estadoInicial, $autorizaSistema);

        return $papeleta;
    }

    private function validarMotivoYRegimen(User $trabajador, Motivo $motivo, array $datos): void
    {

        if (! in_array($trabajador->regimen, ['276', '728'], true)) {
            throw new PapeletaException(
                'Tu usuario no tiene un régimen (276 o 728) asignado. Comunícate con Administración para regularizarlo antes de crear una papeleta.'
            );
        }

        if (! $motivo->activo) {
            throw new PapeletaException("El motivo {$motivo->nombre} ya no está disponible.");
        }

        if ($motivo->adjunto === 'obligatorio' && empty($datos['justificacion'] ?? null)) {
            throw new PapeletaException("La justificación es obligatoria para el motivo {$motivo->nombre}.");
        }
    }

    private function resolverJefatura(User $trabajador): array
    {
        $unidad = $trabajador->unidadOrganica;

        $directoARrhh = $trabajador->esJefeDeArea();

        if ($directoARrhh && ! User::role('rrhh')->where('activo', true)->exists()) {
            throw new PapeletaException(
                'No hay personal de RR. HH. activo para recibir tu papeleta. Comunícate con Administración antes de crearla.'
            );
        }

        [$jefeInmediatoResuelto, $jefeAreaId] = (! $directoARrhh && $unidad)
            ? $unidad->jefaturasDe($trabajador)
            : [null, null];

        $jefeInmediato = $jefeInmediatoResuelto !== null
            ? User::whereKey($jefeInmediatoResuelto)->where('activo', true)->first()
            : null;

        $esJefeInmediatoDeLaUnidad = $unidad?->esJefeDeLaUnidad($trabajador) ?? false;

        if (! $directoARrhh && ! $jefeInmediato) {
            if ($esJefeInmediatoDeLaUnidad) {
                throw new PapeletaException(
                    'Tu unidad no tiene un Jefe de Área activo asignado. Comunícate con Administración para regularizar la jefatura antes de crear una papeleta.'
                );
            }

            throw new PapeletaException(
                'Tu unidad no tiene un jefe activo asignado. Comunícate con Administración para regularizar la jefatura antes de crear una papeleta.'
            );
        }

        return [
            'directoARrhh' => $directoARrhh,
            'jefeInmediatoId' => $jefeInmediato?->id,
            'jefeAreaId' => $jefeAreaId,
            'esJefeInmediatoDeLaUnidad' => $esJefeInmediatoDeLaUnidad,
            'rrhhEnHorario' => $this->horarioRrhh->estaEnHorarioAhora(),
        ];
    }

    /**
     * @param  array{directoARrhh: bool, rrhhEnHorario: bool}  $jefatura
     * @return class-string<PendienteJefe|PendienteRrhh|AutorizadaYCorriendo>
     */
    private function determinarEstadoInicial(array $jefatura): string
    {

        //

        return match (true) {
            $jefatura['directoARrhh'] && ! $jefatura['rrhhEnHorario'] => AutorizadaYCorriendo::class,
            $jefatura['directoARrhh'] => PendienteRrhh::class,
            default => PendienteJefe::class,
        };
    }

    private function crearPapeletaConHistorial(
        User $trabajador,
        Motivo $motivo,
        array $datos,
        ?Turno $turno,
        Carbon $finTurno,
        array $jefatura,
        string $estadoInicial,
        bool $autorizaSistema,
    ): Papeleta {

        User::whereKey($trabajador->id)->lockForUpdate()->first();
        $this->exigirSinPapeletaViva($trabajador);

        $campos = [
            'trabajador_id' => $trabajador->id,
            'motivo_id' => $motivo->id,
            'sede_id' => $trabajador->sede_id,
            'regimen' => $trabajador->regimen,
            'dia_operativo' => $turno?->fecha ?? now()->toDateString(),
            'fin_turno_at' => $finTurno,
            'estado' => $estadoInicial,
            'jefe_inmediato_id' => $jefatura['jefeInmediatoId'],
            'jefe_area_id' => $jefatura['jefeAreaId'],
            'hora_retorno_estimado' => $datos['hora_retorno_estimado'] ?? null,
            'justificacion' => $datos['justificacion'] ?? null,
            'adjunto_inicial_path' => $datos['adjunto_inicial_path'] ?? null,
            'autorizado_con_rrhh_fuera_horario' => $autorizaSistema,
            'hora_salida_real' => $autorizaSistema ? now() : null,
        ];

        if ($autorizaSistema) {
            $campos['revision_posthoc_estado'] = 'pendiente';
        }

        $papeleta = Papeleta::create($campos);

        HistorialPapeleta::create([
            'papeleta_id' => $papeleta->id,
            'actor_id' => $trabajador->id,
            'actor_tipo' => 'trabajador',
            'estado_nuevo' => class_basename($papeleta->estado),
            'motivo_nuevo_id' => $motivo->id,
            'justificacion' => $datos['justificacion'] ?? null,
        ]);

        $this->registrarHistorialDeEscalamiento($papeleta, $jefatura, $autorizaSistema);

        return $papeleta;
    }

    private function registrarHistorialDeEscalamiento(Papeleta $papeleta, array $jefatura, bool $autorizaSistema): void
    {
        if ($jefatura['directoARrhh']) {
            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => null,
                'actor_tipo' => 'sistema',
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $autorizaSistema
                    ? 'Jefe de área con RRHH fuera de horario: autorización automática, pendiente de revisión post-hoc.'
                    : 'Jefe de área: la papeleta se envía directo a RRHH.',
            ]);

            return;
        }

    }

    private function notificarCreacion(Papeleta $papeleta, string $estadoInicial, bool $autorizaSistema): void
    {
        match (true) {
            $autorizaSistema => (function () use ($papeleta) {
                $this->notificar->puedeSalir($papeleta);
                $this->notificar->revisionPosthocPendiente($papeleta);
            })(),
            $estadoInicial === PendienteRrhh::class => $this->notificar->pendienteDeRrhh($papeleta),
            default => $this->notificar->creada($papeleta),
        };
    }

    private function exigirSinPapeletaViva(User $trabajador): void
    {
        $viva = Papeleta::vivaDe($trabajador);

        if ($viva) {
            throw new PapeletaException($viva->mensajeDeBloqueoParaNueva());
        }
    }

    private function resolverTurnoActivo(User $trabajador): ?Turno
    {
        if ($trabajador->regimen === '728') {

            $turno = Turno::vigenteParaUsuario($trabajador->id);

            if (! $turno || $turno->es_descanso) {
                throw new PapeletaException(
                    'No puedes crear una papeleta en este momento: no tienes un turno vigente asignado. '.
                    'Comunícate con tu jefe para que regularice tu turno.'
                );
            }

            return $turno;
        }

        if (! app(HorarioOrdinarioService::class)->estaDentroDeVentana()) {
            throw new PapeletaException(
                'Estás fuera de tu horario ordinario en este momento (o hoy no es día laborable). '.
                'No puedes crear una papeleta fuera de horario.'
            );
        }

        return null;
    }

    private function resolverFinDeTurno(User $trabajador, ?Turno $turno): Carbon
    {
        if ($trabajador->regimen === '728') {
            return $turno->finReal() ?? now()->endOfDay();
        }

        return app(HorarioOrdinarioService::class)->finDelDia(now());
    }
}
