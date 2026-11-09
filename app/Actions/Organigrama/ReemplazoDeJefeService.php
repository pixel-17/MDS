<?php

declare(strict_types=1);

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;


class ReemplazoDeJefeService
{
    /**
     * Trabajadores activos de la unidad, sin contar a $jefe.
     *
     * @return HasMany<User, UnidadOrganica>
     */
    public function miembrosActivos(
        UnidadOrganica $unidad,
        User $jefe
    ): HasMany {
        return $unidad->miembros()
            ->where('activo', true)
            ->whereKeyNot($jefe->id);
    }

    
    public function requiereReemplazo(UnidadOrganica $unidad, User $jefe): bool
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return false;
        }

        return $this->candidatosElegibles($unidad, $jefe)->isNotEmpty();
    }

    /**
     * Quienes pueden relevarlo: los trabajadores activos de la unidad y los
     * jefes de sus sub-unidades (un jefe de área puede ser relevado por otro
     * jefe). Solo los que pasan las reglas de cambio de jefe.
     *
     * @return Collection<int, User>
     */
    public function candidatos(UnidadOrganica $unidad, User $jefe, ?bool $requiere = null): Collection
    {
        
        if ($requiere === false) {
            return collect();
        }

        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return collect();
        }

        return $this->candidatosElegibles($unidad, $jefe);
    }

    /**
     * Cómputo real de candidatos elegibles, sin pasar por requiereReemplazo()
     * (que a su vez usa este método): lo usan tanto requiereReemplazo() como
     * candidatos() para no duplicar la consulta ni recursionar.
     *
     * @return Collection<int, User>
     */
    private function candidatosElegibles(UnidadOrganica $unidad, User $jefe): Collection
    {
        $jefesDeHijos = $unidad->hijos()->whereNotNull('jefe_id')->pluck('jefe_id');

        return User::query()
            ->where('activo', true)
            ->whereKeyNot($jefe->id)
            ->where(fn ($q) => $q
                ->where('unidad_organica_id', $unidad->id)
                ->orWhereIn('id', $jefesDeHijos))
            ->orderBy('name')->orderBy('apellido')
            ->get()
            ->filter(function (User $candidato) use ($unidad) {
                try {
                    app(JefaturaUnidadService::class)->validar(
                        $unidad,
                        (int) $candidato->id,
                        null,
                        (int) $candidato->unidad_organica_id !== (int) $unidad->id,
                    );

                    return true;
                } catch (UsuarioException) {
                    return false;
                }
            })
            ->values();
    }

    /**
     * Quién releva a $jefe en $unidad: el elegido, o null si no se elige a
     * nadie. Dejar reemplazo es SIEMPRE opcional (haya o no candidatos): si
     * no se elige a nadie, la unidad simplemente se queda sin jefe.
     *
     * @param  array<int|string, int|string|null>  $reemplazos
     *
     * @throws UsuarioException
     */
    public function reemplazoPara(UnidadOrganica $unidad, User $jefe, array $reemplazos): ?int
    {
        if ((int) $unidad->jefe_id !== (int) $jefe->id) {
            return null;
        }

        $elegido = (int) ($reemplazos[$unidad->id] ?? 0);

        if ($elegido === 0) {
            
            return null;
        }

        if ($elegido === (int) $jefe->id) {
            throw new UsuarioException('El reemplazo de «'.$unidad->nombre.'» no puede ser la misma persona.');
        }

        
        $candidato = User::find($elegido)
            ?? throw new UsuarioException('El reemplazo elegido para «'.$unidad->nombre.'» ya no existe.');

        app(JefaturaUnidadService::class)->validar(
            $unidad,
            $elegido,
            null,
            (int) $candidato->unidad_organica_id !== (int) $unidad->id,
        );

        return $elegido;
    }

    /**
     * Jefe actual del destino (distinto de $jefe), o vacío si no tiene.
     *
     * @return Collection<int, User>
     */
    /**
     * Jefe actual del destino, distinto de $excepto.
     *
     * @return Collection<int, User>
     */
    public function jefesDelDestino(
        UnidadOrganica $destino,
        User $excepto
    ): Collection {
        if ($destino->jefe_id === null) {
            return collect();
        }

        $jefe = User::find($destino->jefe_id);

        if ($jefe === null) {
            return collect();
        }

        if ((int) $jefe->id === (int) $excepto->id) {
            return collect();
        }

        return collect([$jefe]);
    }

    
    public function requiereReemplazarDestino(UnidadOrganica $destino, User $jefe): bool
    {
        return $this->jefesDelDestino($destino, $jefe)->isNotEmpty();
    }

    
    public function validarReemplazoDestino(UnidadOrganica $destino, User $jefe, ?int $elegidoId): ?User
    {
        return $this->jefesDelDestino($destino, $jefe)->first();
    }
}
