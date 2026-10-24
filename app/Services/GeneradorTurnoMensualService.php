<?php

namespace App\Services;

use App\Events\HorarioActualizado;
use App\Models\CargaTurnoMensual;
use App\Models\Configuracion;
use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeneradorTurnoMensualService
{

    public function cargarConfiguracion(
        User $trabajador,
        string $turno,
        Carbon $fechaAncla,
        User $actor,
        int $diasTrabajo = 6,
        int $diasDescanso = 1,
    ): ConfiguracionTurno {
        if (! in_array($turno, ConfiguracionTurno::turnosValidosPara($trabajador), true)) {
            throw ValidationException::withMessages([
                'turno' => 'Ese turno no es válido para el régimen '.$trabajador->regimen.' de este trabajador.',
            ]);
        }

        if (! $trabajador->activo) {
            throw ValidationException::withMessages([
                'turno' => 'Este trabajador está marcado como inactivo; no se le puede asignar un turno.',
            ]);
        }

        $config = ConfiguracionTurno::updateOrCreate(
            ['user_id' => $trabajador->id],
            [
                'turno' => $turno,
                'fecha_ancla' => $fechaAncla->toDateString(),
                'dias_trabajo' => $diasTrabajo,
                'dias_descanso' => $diasDescanso,
                'actualizado_por_id' => $actor->id,
            ]
        );

        $this->generarMes($trabajador, $fechaAncla->copy()->startOfMonth(), $actor, 'manual');

        return $config;
    }

    public function generarMes(
        User $trabajador,
        Carbon $mesInicio,
        ?User $actor,
        string $origen = 'automatico',
        ?ConfiguracionTurno $config = null,
    ): void {
        if ($origen === 'automatico' && ! $trabajador->activo) {
            return;
        }

        $config ??= ConfiguracionTurno::where('user_id', $trabajador->id)->first();

        if (! $config) {
            return;
        }

        $anio = $mesInicio->year;
        $mes = $mesInicio->month;

        if ($origen === 'automatico') {
            $yaTieneCargaEsteMes = CargaTurnoMensual::where('user_id', $trabajador->id)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->exists();

            if ($yaTieneCargaEsteMes) {
                return;
            }
        }

        [$horaInicio, $horaFin] = $this->horasDe($config->turno);

        $dia = $mesInicio->copy()->startOfMonth();
        $finDeMes = $mesInicio->copy()->endOfMonth();
        $ahora = now();
        $filas = [];

        while ($dia->lessThanOrEqualTo($finDeMes)) {
            $esDescanso = self::esDescansoEnFecha($config->fecha_ancla, $dia, $config->dias_trabajo, $config->dias_descanso);

            $filas[] = [
                'user_id' => $trabajador->id,
                'fecha' => $dia->toDateString(),
                'sede_id' => $trabajador->sede_id,
                'es_descanso' => $esDescanso,
                'turno' => $esDescanso ? null : $config->turno,
                'hora_inicio' => $esDescanso ? null : $horaInicio,
                'hora_fin' => $esDescanso ? null : $horaFin,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];

            $dia->addDay();
        }

        Turno::upsert(
            $filas,
            ['user_id', 'fecha'],
            ['sede_id', 'es_descanso', 'turno', 'hora_inicio', 'hora_fin', 'updated_at']
        );

        CargaTurnoMensual::updateOrCreate(
            ['user_id' => $trabajador->id, 'anio' => $anio, 'mes' => $mes],
            [
                'origen' => $origen,
                'configuracion_turno_id' => $config->id,
                'generado_por_id' => $actor?->id,
            ]
        );

        HorarioActualizado::notificar($trabajador->id);
    }

    public static function esDescansoEnFecha(Carbon $fechaAncla, Carbon $dia, int $diasTrabajo, int $diasDescanso): bool
    {
        $ciclo = $diasTrabajo + $diasDescanso;

        $offset = $fechaAncla->diffInDays($dia, absolute: false);
        $posicion = (($offset % $ciclo) + $ciclo) % $ciclo;

        return $posicion >= $diasTrabajo;
    }

    public function retirarTurno(User $trabajador, ?Carbon $desde = null): void
    {
        $desde = ($desde ?? now())->copy()->startOfDay();

        DB::transaction(function () use ($trabajador, $desde) {
            Turno::where('user_id', $trabajador->id)
                ->where('fecha', '>=', $desde->toDateString())
                ->delete();

            CargaTurnoMensual::where('user_id', $trabajador->id)
                ->whereRaw('(anio * 12 + mes) >= ?', [$desde->year * 12 + $desde->month])
                ->delete();

            ConfiguracionTurno::where('user_id', $trabajador->id)->delete();
        });

        HorarioActualizado::notificar($trabajador->id);
    }

    public function generarProximoMesParaTodos(): void
    {
        $proximoMes = now()->addMonthNoOverflow()->startOfMonth();

        $configs = ConfiguracionTurno::with('usuario')
            ->whereHas('usuario', fn ($q) => $q->where('activo', true))
            ->get();

        if ($configs->isEmpty()) {
            return;
        }

        $userIdsConCarga = CargaTurnoMensual::where('anio', $proximoMes->year)
            ->where('mes', $proximoMes->month)
            ->whereIn('user_id', $configs->pluck('user_id'))
            ->pluck('user_id')
            ->all();

        $configs
            ->reject(fn (ConfiguracionTurno $config) => in_array($config->user_id, $userIdsConCarga, true))
            ->each(function (ConfiguracionTurno $config) use ($proximoMes) {
                if ($config->usuario) {
                    $this->generarMes(
                        $config->usuario,
                        $proximoMes->copy(),
                        actor: null,
                        origen: 'automatico',
                        config: $config,
                    );
                }
            });
    }

    public static function claveDeHora(string $turno, string $extremo): string
    {
        return in_array($turno, ['MANANA', 'TARDE', 'NOCHE'], true)
            ? "TURNO_{$turno}_HORA_{$extremo}"
            : "HORARIO_ORDINARIO_HORA_{$extremo}";
    }

    public function horasDe(string $turno): array
    {
        return match ($turno) {
            'MANANA' => [
                (string) Configuracion::valorDe('TURNO_MANANA_HORA_INICIO', '06:00'),
                (string) Configuracion::valorDe('TURNO_MANANA_HORA_FIN', '14:00'),
            ],
            'TARDE' => [
                (string) Configuracion::valorDe('TURNO_TARDE_HORA_INICIO', '14:00'),
                (string) Configuracion::valorDe('TURNO_TARDE_HORA_FIN', '22:00'),
            ],
            'NOCHE' => [
                (string) Configuracion::valorDe('TURNO_NOCHE_HORA_INICIO', '22:00'),
                (string) Configuracion::valorDe('TURNO_NOCHE_HORA_FIN', '06:00'),
            ],
            default => [
                (string) Configuracion::valorDe('HORARIO_ORDINARIO_HORA_INICIO', HorarioOrdinarioService::HORA_INICIO_DEFECTO),
                (string) Configuracion::valorDe('HORARIO_ORDINARIO_HORA_FIN', HorarioOrdinarioService::HORA_FIN_DEFECTO),
            ],
        };
    }
}
