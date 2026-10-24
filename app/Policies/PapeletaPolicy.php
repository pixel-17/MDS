<?php

namespace App\Policies;

use App\Actions\Papeleta\RrhhHorarioService;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Models\User;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\Finalizada;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;

class PapeletaPolicy
{

    private function esPropia(User $user, Papeleta $papeleta): bool
    {
        return (int) $papeleta->trabajador_id === (int) $user->id;
    }

    private function esJefeVigenteDe(User $user, Papeleta $papeleta): bool
    {
        return User::equipoDe($user)->whereKey($papeleta->trabajador_id)->exists();
    }

    public function view(User $user, Papeleta $papeleta): bool
    {

        return $user->hasRole('admin')
            || $user->hasRole('rrhh')
            || $papeleta->trabajador_id === $user->id
            || $this->esJefeVigenteDe($user, $papeleta);
    }

    public function verArchivos(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh')
            || $papeleta->trabajador_id === $user->id
            || $this->esJefeVigenteDe($user, $papeleta);
    }

    public function crear(User $user): bool
    {

        return $user->hasRole('trabajador') && ! $user->hasRole('admin');
    }

    public function cancelar(User $user, Papeleta $papeleta): bool
    {
        if ($papeleta->trabajador_id !== $user->id) {
            return false;
        }

        if ($papeleta->estado->equals(PendienteJefe::class, ObservadaPorJefe::class)) {
            return true;
        }

        return $papeleta->estado->equals(PendienteRrhh::class, ObservadaPorRrhh::class)
            && app(RrhhHorarioService::class)->estaEnHorarioAhora();
    }

    public function subsanarObservacion(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->trabajador_id === $user->id
            && $papeleta->estado->equals(ObservadaPorJefe::class);
    }

    public function decidirComoJefe(User $user, Papeleta $papeleta): bool
    {
        if ($this->esPropia($user, $papeleta)) {
            return false;
        }

        if (! $papeleta->estado->equals(PendienteJefe::class, ObservadaPorJefe::class, ObservadaPorRrhh::class)) {
            return false;
        }

        return $papeleta->tieneComoJefeInmediatoA($user);
    }

    public function reconocerObservacionRrhh(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->estado->equals(ObservadaPorRrhh::class)
            && ! $this->esPropia($user, $papeleta)
            && $papeleta->tieneComoJefeInmediatoA($user);
    }

    public function responderPosthoc(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->puedeResponderPosthoc($user);
    }

    public function decidirComoRrhh(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh')
            && ! $this->esPropia($user, $papeleta)
            && $papeleta->estado->equals(PendienteRrhh::class);
    }

    public function revisarPosthoc(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh') && ! $this->esPropia($user, $papeleta);
    }

    public function marcarRetorno(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->trabajador_id === $user->id;
    }

    public function marcarRetornoManual(User $user, Papeleta $papeleta): bool
    {
        return ! $this->esPropia($user, $papeleta)
            && $papeleta->tieneComoJefeInmediatoA($user);
    }

    public function revisarSustento(User $user, Sustento $sustento): bool
    {
        return ! $this->esPropia($user, $sustento->papeleta)
            && $user->hasRole('rrhh');
    }

    public function verSustento(User $user, Sustento $sustento): bool
    {
        $papeleta = $sustento->papeleta;

        return $papeleta->trabajador_id === $user->id
            || $this->revisarSustento($user, $sustento)
            || $user->hasRole('admin')
            || $this->esJefeVigenteDe($user, $papeleta);
    }

    public function corregirComoRrhh(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh')
            && ! $this->esPropia($user, $papeleta)
            && ($papeleta->estado->equals(Cerrada::class)
                || $papeleta->estado->equals(Finalizada::class));
    }
}
