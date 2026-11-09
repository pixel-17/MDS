<?php

namespace App\Services;

use App\Events\HorarioActualizado;
use App\Models\CargaTurnoMensual;
use App\Models\Configuracion;
use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Support\PatronDescansoSemanal;
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

        
        
        
        
        
        
        
        
        
        
        
        if ($trabajador->regimen === '276') {
            $ciclo = self::cicloOrdinario276($fechaAncla);
            $fechaAncla = $ciclo['fecha_ancla'];
            $diasTrabajo = $ciclo['dias_trabajo'];
            $diasDescanso = $ciclo['dias_descanso'];
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

    /**
     * Ciclo (dias_trabajo/dias_descanso/fecha_ancla) del horario
     * ordinario global de régimen 276, calculado SIEMPRE desde
     * HORARIO_ORDINARIO_DIAS_LABORABLES. Única fuente de verdad: tanto
     * cargarConfiguracion() (que la impone) como la carga por lotes de
     * CalendarioEquipoIndex la usan, para no tener el cálculo
     * duplicado y en riesgo de desalinearse.
     *
     * $referencia ancla la semana que se usa como base (normalmente
     * "ahora" o la fecha que mandó el formulario); el resultado siempre
     * cae en esa semana o la siguiente, nunca antes.
     *
     * @return array{dias_trabajo: int, dias_descanso: int, fecha_ancla: Carbon}
     */
    public static function cicloOrdinario276(?Carbon $referencia = null): array
    {
        $referencia ??= now();

        $diasLaborables = array_map(
            'intval',
            array_filter(array_map('trim', explode(
                ',',
                (string) Configuracion::valorDe('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5')
            )))
        );

        
        
        $todosLosDias = [1, 2, 3, 4, 5, 6, 7];
        $diasDescansoIso = array_values(array_diff($todosLosDias, $diasLaborables));

        $inicioSemana = $referencia->copy()->startOfWeek();

        $resultado = $diasDescansoIso !== []
            ? PatronDescansoSemanal::anclaYCiclo($diasDescansoIso, $inicioSemana)
            : null;

        return [
            'dias_trabajo' => $resultado['dias_trabajo'] ?? count($diasLaborables),
            'dias_descanso' => $resultado['dias_descanso'] ?? (7 - count($diasLaborables)),
            'fecha_ancla' => $resultado['fecha_ancla'] ?? $inicioSemana,
        ];
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
        $mesEnCurso = now()->startOfMonth();
        $proximoMes = now()->addMonthNoOverflow()->startOfMonth();

        $configs = ConfiguracionTurno::with('usuario')
            ->whereHas('usuario', fn ($q) => $q->where('activo', true))
            ->get();

        if ($configs->isEmpty()) {
            return;
        }

        
        
        
        $cargasExistentes = CargaTurnoMensual::whereIn('user_id', $configs->pluck('user_id'))
            ->where(function ($q) use ($mesEnCurso, $proximoMes) {
                $q->where(fn ($q2) => $q2->where('anio', $mesEnCurso->year)->where('mes', $mesEnCurso->month))
                    ->orWhere(fn ($q2) => $q2->where('anio', $proximoMes->year)->where('mes', $proximoMes->month));
            })
            ->get()
            ->map(fn (CargaTurnoMensual $carga) => "{$carga->user_id}-{$carga->anio}-{$carga->mes}")
            ->all();

        $configs->each(function (ConfiguracionTurno $config) use ($mesEnCurso, $proximoMes, $cargasExistentes) {
            for ($mes = $mesEnCurso->copy(); $mes->lessThanOrEqualTo($proximoMes); $mes->addMonthNoOverflow()) {
                $clave = "{$config->user_id}-{$mes->year}-{$mes->month}";

                if (in_array($clave, $cargasExistentes, true)) {
                    continue;
                }

                $this->generarMes(
                    $config->usuario,
                    $mes->copy(),
                    actor: null,
                    origen: 'automatico',
                    config: $config,
                );
            }
        });
    }

    /**
     * Clave de `configuraciones` donde se EDITA la hora de un turno (la usa
     * DefinicionTurnos). Mañana/Tarde/Noche: TURNO_<CODIGO>_HORA_*. Día (276)
     * usa el horario ordinario global, la misma ventana que bloquea
     * HorarioOrdinarioService, así que no puede divergir.
     *
     * @param  'INICIO'|'FIN'  $extremo
     */
    public static function claveDeHora(string $turno, string $extremo): string
    {
        return in_array($turno, ['MANANA', 'TARDE', 'NOCHE'], true)
            ? "TURNO_{$turno}_HORA_{$extremo}"
            : "HORARIO_ORDINARIO_HORA_{$extremo}";
    }

    /**
     * Horas de cada turno del catálogo, en `configuraciones`.
     *
     * Las claves van LITERALES a propósito: ConfiguracionSinDesajusteTest
     * busca las llamadas a valorDe con texto literal para comprobar que
     * toda clave sembrada se lea. No construirlas dinámicamente.
     *
     * @return array{0: string, 1: string} [hora_inicio, hora_fin] en H:i
     */
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
