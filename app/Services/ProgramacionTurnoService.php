<?php

namespace App\Services;

use App\Events\HorarioActualizado;
use App\Models\CargaTurnoMensual;
use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Support\PatronTurnos;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramacionTurnoService
{
    public const DESCANSO = 'DESCANSO';

    public const MIN_DESCANSO_HORAS = 12;

    public const MAX_DIAS_SEGUIDOS = 6;

    public function __construct(private GeneradorTurnoMensualService $generador) {}

    public function guardarMes(User $trabajador, int $anio, int $mes, array $dias, User $actor): void
    {
        if (! $actor->puedeGestionarTurnoDe($trabajador)) {
            throw ValidationException::withMessages([
                'dias' => 'No tienes permiso para programar el turno de este trabajador.',
            ]);
        }

        $this->validar($trabajador, $anio, $mes, $dias);

        $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();
        $ahora = now();

        $horas = $this->horas();

        $filas = [];
        foreach ($dias as $fecha => $codigo) {
            $descanso = $codigo === self::DESCANSO;

            $filas[] = [
                'user_id' => $trabajador->id,
                'fecha' => $fecha,
                'sede_id' => $trabajador->sede_id,
                'es_descanso' => $descanso,
                'turno' => $descanso ? null : $codigo,
                'hora_inicio' => $descanso ? null : $horas[$codigo][0],
                'hora_fin' => $descanso ? null : $horas[$codigo][1],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::transaction(function () use ($trabajador, $inicioMes, $finMes, $filas, $dias, $anio, $mes, $actor) {

            Turno::where('user_id', $trabajador->id)
                ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
                ->when($dias !== [], fn ($q) => $q->whereNotIn('fecha', array_keys($dias)))
                ->delete();

            if ($filas !== []) {
                Turno::upsert(
                    $filas,
                    ['user_id', 'fecha'],
                    ['sede_id', 'es_descanso', 'turno', 'hora_inicio', 'hora_fin', 'updated_at']
                );
            }

            CargaTurnoMensual::updateOrCreate(
                ['user_id' => $trabajador->id, 'anio' => $anio, 'mes' => $mes],
                [
                    'origen' => 'manual',
                    'configuracion_turno_id' => null,
                    'generado_por_id' => $actor->id,
                ]
            );
        });

        HorarioActualizado::notificar($trabajador->id);
    }

    public function planPatron(User $trabajador, array $pasos, string $desde, int $meses, bool $continuar, array $diasActuales = []): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $desde)->startOfDay();
        $meses = max(1, min(PatronTurnos::MAX_MESES, $meses));
        $largo = count($pasos);

        $offset = 0;
        if ($continuar && $largo > 0) {
            $guardados = Turno::where('user_id', $trabajador->id)
                ->whereBetween('fecha', [
                    $inicio->copy()->subDays($largo)->toDateString(),
                    $inicio->copy()->subDay()->toDateString(),
                ])
                ->get()
                ->mapWithKeys(fn (Turno $t) => [$t->fecha->toDateString() => $t->codigo()]);

            $previos = [];
            for ($i = $largo; $i >= 1; $i--) {
                $fecha = $inicio->copy()->subDays($i)->toDateString();
                $previos[] = $diasActuales[$fecha] ?? $guardados[$fecha] ?? null;
            }

            $offset = PatronTurnos::offsetContinuacion($pasos, $previos);
        }

        $ultimo = $inicio->copy()->addMonthsNoOverflow($meses - 1)->endOfMonth();

        $plan = [];
        foreach (PatronTurnos::generar($pasos, $inicio->toDateString(), $ultimo->toDateString(), $offset) as $fecha => $codigo) {
            $plan[substr($fecha, 0, 7)][$fecha] = $codigo;
        }

        $claveInicial = $inicio->format('Y-m');
        $anteriores = array_filter(
            $diasActuales,
            fn ($codigo, $fecha) => is_string($fecha) && str_starts_with($fecha, $claveInicial) && $fecha < $desde,
            ARRAY_FILTER_USE_BOTH
        );
        $plan[$claveInicial] = array_merge($anteriores, $plan[$claveInicial]);
        ksort($plan[$claveInicial]);

        return $plan;
    }

    public function advertenciasPlan(array $plan): array
    {
        return $this->advertencias($plan === [] ? [] : array_merge(...array_values($plan)));
    }

    public function guardarPlan(User $trabajador, array $plan, User $actor): void
    {
        foreach ($plan as $clave => $dias) {
            if (preg_match('/^(\d{4})-(\d{2})$/', (string) $clave, $m) !== 1 || ! is_array($dias)) {
                throw ValidationException::withMessages(['dias' => 'La programación contiene un mes inválido.']);
            }

            $this->validar($trabajador, (int) $m[1], (int) $m[2], $dias);
        }

        DB::transaction(function () use ($trabajador, $plan, $actor) {
            foreach ($plan as $clave => $dias) {
                [$anio, $mes] = array_map('intval', explode('-', (string) $clave));
                $this->guardarMes($trabajador, $anio, $mes, $dias, $actor);
            }
        });
    }

    public function guardarEquipo(Collection $equipo, int $anio, int $mes, array $porUsuario, User $actor): void
    {
        $this->validarEquipo($equipo, $anio, $mes, $porUsuario);

        DB::transaction(function () use ($equipo, $anio, $mes, $porUsuario, $actor) {
            foreach ($porUsuario as $userId => $dias) {
                $this->guardarMes($equipo->get((int) $userId), $anio, $mes, $dias, $actor);
            }
        });
    }

    /**
     * @param  Collection<int, User>  $equipo
     * @param  array<int|string, array<string, string>>  $porUsuario
     */
    public function validarEquipo(Collection $equipo, int $anio, int $mes, array $porUsuario): void
    {
        foreach ($porUsuario as $userId => $dias) {
            $trabajador = $equipo->get((int) $userId);

            if (! $trabajador || ! is_array($dias)) {
                throw ValidationException::withMessages([
                    'dias' => 'La programación incluye a un trabajador que no está en tu equipo.',
                ]);
            }

            try {
                $this->validar($trabajador, $anio, $mes, $dias);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    'dias' => $trabajador->nombre_completo.': '.($e->errors()['dias'][0] ?? 'programación inválida.'),
                ]);
            }
        }
    }

    public function advertenciasEquipo(Collection $equipo, array $porUsuario): array
    {
        $horas = $this->horas();
        $salida = [];

        foreach ($porUsuario as $userId => $dias) {
            $nombre = $equipo->get((int) $userId)?->nombre_completo ?? '#'.$userId;

            foreach (self::calcularAdvertencias($dias, $horas) as $advertencia) {
                $salida[] = $nombre.': '.$advertencia;
            }
        }

        return $salida;
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function horas(): array
    {
        $horas = [];
        foreach (ConfiguracionTurno::TURNOS_728 as $codigo) {
            $horas[$codigo] = $this->generador->horasDe($codigo);
        }

        return $horas;
    }

    public function advertencias(array $dias): array
    {
        $horas = $this->horas();

        return self::calcularAdvertencias($dias, $horas);
    }

    public static function calcularAdvertencias(array $dias, array $horas): array
    {
        ksort($dias);

        $nombres = [1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom'];
        $etiqueta = fn (DateTimeImmutable $f) => $nombres[(int) $f->format('N')].' '.$f->format('j');
        $nombreTurno = ['MANANA' => 'Mañana', 'TARDE' => 'Tarde', 'NOCHE' => 'Noche'];

        $advertencias = [];
        $racha = 0;
        $inicioRacha = null;
        $rachaAvisada = false;
        $previa = null; // [DateTimeImmutable $fecha, string $codigo]

        foreach ($dias as $fechaStr => $codigo) {
            $fecha = new DateTimeImmutable($fechaStr);
            $trabaja = $codigo !== self::DESCANSO && isset($horas[$codigo]);

            $consecutivo = $previa !== null && $previa[0]->modify('+1 day')->format('Y-m-d') === $fecha->format('Y-m-d');

            if (! $trabaja) {
                $racha = 0;
                $rachaAvisada = false;
                $previa = null;

                continue;
            }

            if (! $consecutivo) {
                $racha = 0;
                $rachaAvisada = false;
            }

            if ($racha === 0) {
                $inicioRacha = $fecha;
            }
            $racha++;

            if ($racha > self::MAX_DIAS_SEGUIDOS && ! $rachaAvisada) {
                $advertencias[] = 'Más de '.self::MAX_DIAS_SEGUIDOS.' días seguidos trabajando desde el '.$etiqueta($inicioRacha).'.';
                $rachaAvisada = true;
            }

            if ($consecutivo) {
                [$fechaPrevia, $codigoPrevio] = $previa;

                $finPrevio = new DateTimeImmutable($fechaPrevia->format('Y-m-d').' '.$horas[$codigoPrevio][1]);
                $inicioPrevio = new DateTimeImmutable($fechaPrevia->format('Y-m-d').' '.$horas[$codigoPrevio][0]);
                if ($finPrevio <= $inicioPrevio) {
                    $finPrevio = $finPrevio->modify('+1 day');
                }

                $inicioActual = new DateTimeImmutable($fecha->format('Y-m-d').' '.$horas[$codigo][0]);
                $descansoHoras = ($inicioActual->getTimestamp() - $finPrevio->getTimestamp()) / 3600;

                if ($descansoHoras < self::MIN_DESCANSO_HORAS) {
                    $advertencias[] = sprintf(
                        '%s → %s: solo %s h de descanso entre %s y %s.',
                        $etiqueta($fechaPrevia),
                        $etiqueta($fecha),
                        rtrim(rtrim(number_format(max(0, $descansoHoras), 1, '.', ''), '0'), '.') ?: '0',
                        $nombreTurno[$codigoPrevio],
                        $nombreTurno[$codigo],
                    );
                }
            }

            $previa = [$fecha, $codigo];
        }

        return $advertencias;
    }

    /**
     * @param  array<string, string>  $dias
     */
    public function validar(User $trabajador, int $anio, int $mes, array $dias): void
    {
        if ($trabajador->regimen !== '728') {
            throw ValidationException::withMessages([
                'dias' => 'La programación día por día solo aplica al régimen 728.',
            ]);
        }

        if (! $trabajador->activo) {
            throw ValidationException::withMessages([
                'dias' => 'Este trabajador está marcado como inactivo; no se le puede programar turnos.',
            ]);
        }

        $validos = [...ConfiguracionTurno::TURNOS_728, self::DESCANSO];
        $prefijo = sprintf('%04d-%02d-', $anio, $mes);

        foreach ($dias as $fecha => $codigo) {
            $fechaOk = is_string($fecha)
                && str_starts_with($fecha, $prefijo)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1
                && checkdate($mes, (int) substr($fecha, 8, 2), $anio);

            if (! $fechaOk || ! in_array($codigo, $validos, true)) {
                throw ValidationException::withMessages([
                    'dias' => 'La programación contiene un día o un turno inválido.',
                ]);
            }
        }
    }
}
