<?php

namespace App\Actions\Usuario;

use App\Events\PapeletaActualizada;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CrearUsuarioAction
{
    public function __construct(
        private GeneradorTurnoMensualService $generadorTurno,
    ) {}

    /**
     * @param  array{name:string,apellido:string,dni:string,email:string,regimen:string,sede_id:?int,unidad_organica_id:?int,tipo:string,turno:?string,fecha_ancla:?string,dias_trabajo:?int,dias_descanso:?int}  $datos
     */
    public function ejecutar(User $creador, array $datos, bool $esJefeDeArea): User
    {
        $esJefeInmediatoNuevo = $esJefeDeArea && ($datos['tipo'] ?? 'trabajador') === 'jefe_inmediato';

        $nuevo = DB::transaction(function () use ($creador, $datos, $esJefeDeArea, $esJefeInmediatoNuevo) {

            $existente = User::where('dni', $datos['dni'])->lockForUpdate()->first();

            if ($existente) {
                $this->validarReingreso($existente);
            }

            $atributos = [
                'name' => $datos['name'],
                'apellido' => $datos['apellido'],
                'dni' => $datos['dni'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['dni']),
                'debe_actualizar_password' => true,
                'activo' => true,
                'regimen' => $datos['regimen'],
                'sede_id' => $this->resolverSede($creador, $datos, $esJefeDeArea, $esJefeInmediatoNuevo),
                'unidad_organica_id' => $esJefeDeArea ? $datos['unidad_organica_id'] : $creador->unidad_organica_id,
            ];

            if ($existente) {

                $existente->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ])->fill($atributos)->save();

                $nuevo = $existente;
            } else {
                $nuevo = User::create($atributos);
            }

            if (! $nuevo->hasRole('trabajador')) {
                $nuevo->assignRole('trabajador');
                PapeletaActualizada::olvidarDestinatariosFijos();
            }

            if ($esJefeInmediatoNuevo) {
                $this->asignarComoJefeDeUnidad($nuevo, (int) $datos['unidad_organica_id']);
            }

            if ($datos['regimen'] === '728' && ! $esJefeInmediatoNuevo && ! empty($datos['turno'])) {
                $this->generadorTurno->cargarConfiguracion(
                    trabajador: $nuevo,
                    turno: $datos['turno'],
                    fechaAncla: Carbon::parse($datos['fecha_ancla']),
                    actor: $creador,
                    diasTrabajo: (int) ($datos['dias_trabajo'] ?? 6),
                    diasDescanso: (int) ($datos['dias_descanso'] ?? 1),
                );
            }

            return $nuevo->fresh();
        });

        return $nuevo;
    }

    private function validarReingreso(User $existente): void
    {
        if ($existente->activo) {
            throw new UsuarioException('Ya existe un usuario activo con ese DNI.');
        }

        $rolesEspeciales = $existente->getRoleNames()->diff(['trabajador']);

        if ($rolesEspeciales->isNotEmpty()) {
            throw new UsuarioException(
                'Ese DNI corresponde a una cuenta desactivada con permisos especiales (administración o RR. HH.). '.
                'Solo un administrador puede reactivarla desde Usuarios.'
            );
        }
    }

    private function asignarComoJefeDeUnidad(User $nuevo, int $unidadId): void
    {
        $unidad = UnidadOrganica::findOrFail($unidadId);

        if ($unidad->jefe_id) {
            throw new UsuarioException(
                "La unidad \"{$unidad->nombre}\" ya tiene su jefe. Cámbialo desde el organigrama antes de crear otro."
            );
        }

        $unidad->update(['jefe_id' => $nuevo->id]);
    }

    private function resolverSede(User $creador, array $datos, bool $esJefeDeArea, bool $esJefeInmediatoNuevo): ?int
    {
        if ($esJefeInmediatoNuevo) {
            return $datos['sede_id'] ?? $creador->sede_id;
        }

        if (! $esJefeDeArea) {
            return $creador->sede_id;
        }

        $sedeDelJefe = UnidadOrganica::with('jefe')->find($datos['unidad_organica_id'])?->jefe?->sede_id;

        return $sedeDelJefe ?? $creador->sede_id;
    }
}
