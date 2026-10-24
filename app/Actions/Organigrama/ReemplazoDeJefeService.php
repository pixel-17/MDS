<?php

declare(strict_types=1);

namespace App\Actions\Organigrama;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Collection;

class ReemplazoDeJefeService
{

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
