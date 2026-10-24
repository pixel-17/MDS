<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama\Concerns;

use App\Actions\Organigrama\MoverJefeComoTrabajadorAction;
use App\Actions\Organigrama\MoverJefeInmediatoAction;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;

trait MueveJefe
{

    public ?array $propuestaJefe = null;

    public string $modoJefe = '';

    public string|int|null $reemplazaDestino = null;

    public array $reemplazos = [];

    public array $turnoJefe = ['turno' => null, 'fecha_ancla' => null, 'dias_trabajo' => 6, 'dias_descanso' => 1];

    private function proponerMovimientoJefe(User $usuario, User $jefe, UnidadOrganica $destino): void
    {
        $errores = [];
        $posibles = [];
        $unidad = null;

        foreach ([MoverJefeComoTrabajadorAction::COMO_TRABAJADOR, MoverJefeComoTrabajadorAction::COMO_JEFE] as $modo) {
            try {
                app(MoverJefeComoTrabajadorAction::class)->previsualizar($usuario, $jefe, $destino, $modo);
                $posibles[] = $modo;
            } catch (UsuarioException $e) {
                $errores[] = $e->getMessage();
            }
        }

        try {
            $unidad = app(MoverJefeInmediatoAction::class)->previsualizar($usuario, $jefe, $destino);
            $posibles[] = 'unidad';
        } catch (UsuarioException $e) {
            $errores[] = $e->getMessage();
        }

        if ($posibles === []) {
            $this->mensajeError = implode(' ', array_unique($errores));

            return;
        }

        $this->modoJefe = count($posibles) === 1 ? $posibles[0] : '';
        $this->reemplazos = [];
        $this->reemplazaDestino = null;
        $this->turnoJefe = ['turno' => null, 'fecha_ancla' => now()->toDateString(), 'dias_trabajo' => 6, 'dias_descanso' => 1];

        $this->propuestaJefe = [
            'jefe' => (int) $jefe->id,
            'origen' => (int) ($unidad !== null
                ? $unidad['unidad']->parent_id
                : ($jefe->unidadesQueEncabeza()->value('parent_id') ?? 0)),
            'destino' => (int) $destino->id,
        ];
    }

    public function confirmarMovimientoJefe(): void
    {
        $usuario = $this->autorizar();
        $propuesta = $this->propuestaJefe;
        $modo = $this->modoJefe;
        $reemplazos = $this->reemplazos;
        $reemplazaDestino = ($this->reemplazaDestino !== null && $this->reemplazaDestino !== '') ? (int) $this->reemplazaDestino : null;
        $turno = $this->turnoJefe;

        if (! $this->modoEdicion || ! is_array($propuesta)) {
            return;
        }

        $persona = User::find((int) ($propuesta['jefe'] ?? 0));
        $eraJefe = $persona && ($persona->unidadesQueEncabeza()->exists());

        $this->mensajeOk = $this->mensajeError = null;

        if (! in_array($modo, ['trabajador', 'jefe', 'unidad'], true)) {
            $this->mensajeError = 'Elige cómo quieres mover a la persona.';

            return;
        }

        $this->propuesta = null;
        $this->propuestaJefe = null;

        if ($modo === 'unidad') {
            try {
                $unidad = app(MoverJefeInmediatoAction::class)->ejecutar(
                    $usuario,
                    (int) ($propuesta['jefe'] ?? 0),
                    (int) ($propuesta['destino'] ?? 0),
                    (int) ($propuesta['origen'] ?? 0),
                    true,
                );
            } catch (UsuarioException $e) {
                $this->mensajeError = $e->getMessage();

                return;
            }

            $unidad->load(['jefe', 'padre']);
            $this->mensajeOk = '«'.$unidad->nombre.'» ('.($unidad->jefe?->nombre_completo ?? 'sin jefe').') ahora depende de '
                .($unidad->padre?->nombre ?? '—').'.';

            return;
        }

        try {
            $movimiento = app(MoverJefeComoTrabajadorAction::class)->ejecutar(
                $usuario,
                (int) ($propuesta['jefe'] ?? 0),
                (int) ($propuesta['destino'] ?? 0),
                $reemplazos,
                $turno,
                true,
                $modo,
                $reemplazaDestino,
            );
        } catch (UsuarioException $e) {

            $this->propuestaJefe = $propuesta;
            $this->mensajeError = $e->getMessage();

            return;
        }

        $this->reemplazos = [];
        $this->reemplazaDestino = null;
        $movimiento->load(['trabajador', 'unidadAnterior', 'unidadNueva']);
        $this->mensajeOk = $movimiento->trabajador?->nombre_completo
            .($modo === 'jefe' ? ' pasó a ser jefe inmediato de ' : ' pasó a ser trabajador de ')
            .($movimiento->unidadNueva?->nombre ?? '—')
            .($eraJefe ? '; su unidad anterior conserva a su gente.' : '.');
    }

    private function vistaPreviaMovimientoJefe(User $usuario): ?array
    {
        if ($this->propuestaJefe === null) {
            return null;
        }

        $jefe = User::find((int) ($this->propuestaJefe['jefe'] ?? 0));
        $destino = UnidadOrganica::find((int) ($this->propuestaJefe['destino'] ?? 0));

        if (! $jefe || ! $destino) {
            return null;
        }

        $vista = ['jefe' => $jefe, 'es_jefe' => $jefe->unidadesQueEncabeza()->exists(), 'destino' => $destino, 'trabajador' => null, 'como_jefe' => null, 'unidad' => null, 'errores' => []];

        foreach (['trabajador' => MoverJefeComoTrabajadorAction::COMO_TRABAJADOR, 'como_jefe' => MoverJefeComoTrabajadorAction::COMO_JEFE] as $clave => $modo) {
            try {
                $vista[$clave] = app(MoverJefeComoTrabajadorAction::class)->previsualizar($usuario, $jefe, $destino, $modo);
            } catch (UsuarioException $e) {
                $vista['errores'][$clave] = $e->getMessage();
            }
        }

        try {
            $vista['unidad'] = app(MoverJefeInmediatoAction::class)->previsualizar($usuario, $jefe, $destino);
        } catch (UsuarioException $e) {
            $vista['errores']['unidad'] = $e->getMessage();
        }

        return $vista['trabajador'] === null && $vista['como_jefe'] === null && $vista['unidad'] === null ? null : $vista;
    }
}
