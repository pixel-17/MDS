<?php

declare(strict_types=1);

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\ConfiguracionTurno;
use App\Models\MovimientoOrganigrama;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MoverJefeComoTrabajadorAction
{

    public const COMO_TRABAJADOR = 'trabajador';

    public const COMO_JEFE = 'jefe';

    public function __construct(
        private GeneradorTurnoMensualService $generadorTurno,
        private ReemplazoDeJefeService $reemplazoDeJefe,
    ) {}

    public function previsualizar(User $actor, User $jefe, UnidadOrganica $destino, string $modo = self::COMO_TRABAJADOR): array
    {
        $unidades = $this->validar($actor, $jefe, $destino, $modo);
        $destino->loadMissing('jefe.sede');

        $avisos = [];
        $filas = $unidades->map(function (UnidadOrganica $unidad) use ($jefe, &$avisos) {
            $subunidades = $unidad->hijos()->count();
            $personas = $this->reemplazoDeJefe->miembrosActivos($unidad, $jefe)->count();

            // (JefaturaUnidadService::validar()).
            $candidatos = $this->reemplazoDeJefe->candidatos($unidad, $jefe);

            if ($candidatos->isEmpty()) {
                $avisos[] = '«'.$unidad->nombre.'» '.($personas > 0 || $subunidades > 0
                    ? 'no tiene a quién dejarle el puesto'
                    : 'no tiene más personas').': quedará sin jefe.';
            }

            return [
                'unidad' => $unidad,
                'es_area' => $subunidades > 0,
                'personas' => $personas,
                'subunidades' => $subunidades,
                'requiere_reemplazo' => $candidatos->isNotEmpty(),
                'candidatos' => $candidatos,
            ];
        })->values();

        $comoJefe = $modo === self::COMO_JEFE;
        $jefesDestino = $this->reemplazoDeJefe->jefesDelDestino($destino, $jefe);
        $reemplazaDestino = $comoJefe && $this->reemplazoDeJefe->requiereReemplazarDestino($destino, $jefe);
        $necesitaTurno = ! $comoJefe && $this->necesitaTurno($jefe);

        if ($reemplazaDestino) {
            $avisos[] = '«'.$destino->nombre.'» ya tiene jefe: '.$jefe->nombre_completo.' lo releva y el actual seguirá en la unidad como trabajador.';
        }

        if ($necesitaTurno) {
            $avisos[] = $jefe->nombre_completo.' es 728: como trabajador necesita un turno y su fecha de inicio de ciclo.';
        }

        $sedePropuesta = $destino->jefe?->sede;

        return [
            'modo' => $modo,
            'jefe' => $jefe,
            'destino' => $destino,
            'unidades' => $filas,
            'jefes_destino' => $jefesDestino,
            'requiere_reemplazar_destino' => $reemplazaDestino,
            'sede' => [
                'actual' => $jefe->sede,
                'propuesta' => ! $comoJefe && $sedePropuesta && (int) $sedePropuesta->id !== (int) $jefe->sede_id ? $sedePropuesta : null,
            ],
            'necesita_turno' => $necesitaTurno,
            'turnos_validos' => ConfiguracionTurno::turnosValidosPara($jefe),
            'avisos' => $avisos,
        ];
    }

    public function ejecutar(
        User $actor,
        int $jefeId,
        int $destinoId,
        array $reemplazos,
        ?array $turno,
        bool $confirmado,
        string $modo = self::COMO_TRABAJADOR,
        ?int $reemplazaDestinoId = null,
    ): MovimientoOrganigrama {
        if (! $confirmado) {
            throw new UsuarioException('Falta confirmar explícitamente el movimiento.');
        }

        $comoJefe = $modo === self::COMO_JEFE;

        return DB::transaction(function () use ($actor, $jefeId, $destinoId, $reemplazos, $turno, $modo, $comoJefe, $reemplazaDestinoId) {
            $jefe = User::query()->lockForUpdate()->find($jefeId)
                ?? throw new UsuarioException('La persona ya no existe.');
            $destino = UnidadOrganica::query()->lockForUpdate()->find($destinoId)
                ?? throw new UsuarioException('La unidad de destino ya no existe.');

            $unidades = $this->validar($actor, $jefe, $destino, $modo);

            $plan = $unidades->mapWithKeys(fn (UnidadOrganica $unidad) => [
                $unidad->id => $this->reemplazoDeJefe->reemplazoPara($unidad, $jefe, $reemplazos),
            ]);

            $datosTurno = $comoJefe ? null : $this->validarTurno($jefe, $turno);
            $saliente = $comoJefe ? $this->reemplazoDeJefe->validarReemplazoDestino($destino, $jefe, $reemplazaDestinoId) : null;

            foreach ($unidades as $unidad) {
                $this->relevarJefatura($actor, $unidad, $jefe, $plan[$unidad->id]);
            }

            if ($comoJefe) {
                $jefe->refresh();

                return $this->colocarComoJefe($actor, $jefe, $destino, $saliente);
            }

            $jefe->refresh();

            $movimiento = app(MoverTrabajadorAction::class)->ejecutar(
                $actor,
                (int) $jefe->id,
                (int) $destino->id,
                (int) $jefe->unidad_organica_id,
                true,
                cambiarSede: true,
            );

            if ($datosTurno !== null) {
                $this->generadorTurno->cargarConfiguracion(
                    trabajador: $jefe->fresh(),
                    turno: $datosTurno['turno'],
                    fechaAncla: Carbon::parse($datosTurno['fecha_ancla']),
                    actor: $actor,
                    diasTrabajo: $datosTurno['dias_trabajo'],
                    diasDescanso: $datosTurno['dias_descanso'],
                );
            }

            return $movimiento;
        });
    }

    private function validar(User $actor, User $jefe, UnidadOrganica $destino, string $modo = self::COMO_TRABAJADOR): Collection
    {
        if (! in_array($modo, [self::COMO_TRABAJADOR, self::COMO_JEFE], true)) {
            throw new UsuarioException('Forma de mover no válida.');
        }

        $esAdmin = $actor->hasRole('admin');
        $reglas = app(ReglasOrganigrama::class);

        if (! $esAdmin && ! $actor->esJefeDeArea()) {
            throw new UsuarioException('Solo un administrador o un jefe de área puede mover jefes.');
        }

        if (! $esAdmin) {
            if ($jefe->hasRole('admin')) {
                throw new UsuarioException('No puedes mover a un administrador.');
            }

            if ((int) $jefe->id === (int) $actor->id) {
                throw new UsuarioException('No puedes moverte a ti mismo.');
            }
        }

        $unidades = $jefe->unidadesQueEncabeza()->get();

        if ($modo === self::COMO_TRABAJADOR && $unidades->isEmpty()) {
            throw new UsuarioException($jefe->nombre_completo.' no encabeza ninguna unidad: se mueve como trabajador.');
        }

        if ($unidades->contains('id', $destino->id)) {
            throw new UsuarioException('No puedes mover a '.$jefe->nombre_completo.' a una unidad que él mismo encabeza: elige otra.');
        }

        if ($modo === self::COMO_JEFE && $destino->esJefeDeLaUnidad($jefe)) {
            throw new UsuarioException($jefe->nombre_completo.' ya es jefe inmediato de «'.$destino->nombre.'».');
        }

        if ($modo === self::COMO_TRABAJADOR && (int) $jefe->unidad_organica_id === (int) $destino->id) {
            throw new UsuarioException($jefe->nombre_completo.' ya está en «'.$destino->nombre.'».');
        }

        if ($modo === self::COMO_JEFE && $jefe->sede_id === null && $destino->jefe?->sede_id === null) {
            throw new UsuarioException($jefe->nombre_completo.' no tiene sede: asígnale una antes de hacerlo jefe inmediato (la necesita para los cierres).');
        }

        if (! $destino->activo) {
            throw new UsuarioException('La unidad de destino está desactivada.');
        }

        if ($modo === self::COMO_TRABAJADOR) {
            app(MoverTrabajadorAction::class)->exigirJefeInmediato($destino);
        }

        if (! $esAdmin) {
            $areaDestino = $reglas->areaDe($actor, $destino);

            if ($areaDestino === null) {
                throw new UsuarioException('La unidad de destino está fuera de tu área.');
            }

            $origen = $jefe->unidad_organica_id ? UnidadOrganica::find($jefe->unidad_organica_id) : null;

            if ($origen === null || $reglas->areaDe($actor, $origen) !== $areaDestino) {
                throw new UsuarioException($jefe->nombre_completo.' está fuera de tu área: solo un administrador puede moverlo.');
            }

            foreach ($unidades as $unidad) {
                if (! $reglas->puedeGestionarJefaturasEn($actor, $unidad) || $reglas->areaDe($actor, $unidad) !== $areaDestino) {
                    throw new UsuarioException('«'.$unidad->nombre.'» no depende de ti como jefe de área: solo un administrador puede relevar esa jefatura.');
                }
            }

            if ($modo === self::COMO_JEFE && ! $reglas->puedeGestionarJefaturasEn($actor, $destino)) {
                throw new UsuarioException('No puedes cambiar la jefatura de tu propia unidad: lo hace un administrador.');
            }
        }

        return $unidades;
    }

    private function colocarComoJefe(User $actor, User $jefe, UnidadOrganica $destino, ?User $saliente): MovimientoOrganigrama
    {
        $destino = UnidadOrganica::findOrFail($destino->id);

        $antes = [
            'unidad' => $jefe->unidad_organica_id,
            'sede' => $jefe->sede_id,
            'jefe_inmediato' => $jefe->jefe_inmediato_id,
            'jefe_area' => $jefe->jefe_area_id,
        ];

        app(GuardarUnidadOrganicaAction::class)->ejecutar(
            $actor,
            $destino,
            [
                'nombre' => $destino->nombre,
                'tipo' => $destino->tipo,
                'parent_id' => $destino->parent_id,
                'jefe_id' => $jefe->id,
                'activo' => $destino->activo,
            ],
            ubicarJefe: true,
        );

        $jefe->refresh();
        if ((int) $jefe->unidad_organica_id !== (int) $destino->id || $jefe->sede_id === null) {
            $jefe->unidad_organica_id = $destino->id;
            $jefe->sede_id ??= $destino->jefe?->sede_id;
            $jefe->save();
        }

        $jefe->refresh();

        return MovimientoOrganigrama::create([
            'trabajador_id' => $jefe->id,
            'actor_id' => $actor->id,
            'unidad_anterior_id' => $antes['unidad'],
            'unidad_nueva_id' => $jefe->unidad_organica_id,
            'sede_anterior_id' => $antes['sede'],
            'sede_nueva_id' => $jefe->sede_id,
            'jefe_inmediato_anterior_id' => $antes['jefe_inmediato'],
            'jefe_inmediato_nuevo_id' => $jefe->jefe_inmediato_id,
            'jefe_area_anterior_id' => $antes['jefe_area'],
            'jefe_area_nuevo_id' => $jefe->jefe_area_id,
            'revierte_id' => null,
        ]);
    }

    private function validarTurno(User $jefe, ?array $turno): ?array
    {
        if (! $this->necesitaTurno($jefe)) {
            return null;
        }

        $codigo = $turno['turno'] ?? null;
        $fecha = $turno['fecha_ancla'] ?? null;

        if (! in_array($codigo, ConfiguracionTurno::turnosValidosPara($jefe), true)) {
            throw new UsuarioException('Elige el turno de '.$jefe->nombre_completo.' en la unidad nueva (régimen 728).');
        }

        try {
            Carbon::parse((string) $fecha);
        } catch (\Throwable) {
            throw new UsuarioException('Indica desde qué fecha empieza el ciclo de turno de '.$jefe->nombre_completo.'.');
        }

        return [
            'turno' => $codigo,
            'fecha_ancla' => Carbon::parse((string) $fecha)->toDateString(),
            'dias_trabajo' => max(1, (int) ($turno['dias_trabajo'] ?? 6)),
            'dias_descanso' => max(0, (int) ($turno['dias_descanso'] ?? 1)),
        ];
    }

    private function necesitaTurno(User $jefe): bool
    {
        return $jefe->regimen === '728'
            && ! ConfiguracionTurno::where('user_id', $jefe->id)->exists();
    }

    private function relevarJefatura(User $actor, UnidadOrganica $unidad, User $jefe, ?int $reemplazoId): void
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return;
        }

        app(GuardarUnidadOrganicaAction::class)->ejecutar(
            $actor,
            $unidad,
            [
                'nombre' => $unidad->nombre,
                'tipo' => $unidad->tipo,
                'parent_id' => $unidad->parent_id,
                'jefe_id' => $reemplazoId,
                'activo' => $unidad->activo,
            ],
            ubicarJefe: $reemplazoId !== null,
        );
    }
}
