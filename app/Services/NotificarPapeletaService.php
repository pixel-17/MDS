<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use App\Notifications\PapeletaNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificarPapeletaService
{
    public function creada(Papeleta $papeleta): void
    {
        $this->enviar(
            $this->destinatariosJefe($papeleta),
            $papeleta,
            'creada_pendiente_jefe',
            'Nueva papeleta por aprobar',
            "{$papeleta->trabajador->nombre_completo} solicitó una papeleta de {$papeleta->motivo->nombre}.",
            fn () => $this->urlJefe($papeleta),
        );
    }

    public function pendienteDeRrhh(Papeleta $papeleta): void
    {
        $this->enviar(
            $this->usuariosRrhh(),
            $papeleta,
            'pendiente_rrhh',
            'Papeleta pendiente de tu aprobación',
            "El jefe de {$papeleta->trabajador->nombre_completo} aprobó una papeleta de {$papeleta->motivo->nombre}. Falta tu decisión.",
            fn () => $this->urlRrhh($papeleta),
        );
    }

    public function puedeSalir(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'autorizada',
            'Papeleta autorizada',
            'Tu papeleta fue autorizada. Ya puedes salir.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function revisionPosthocPendiente(Papeleta $papeleta): void
    {
        $this->enviar(
            $this->usuariosRrhh(),
            $papeleta,
            'posthoc_pendiente',
            'Revisión post-hoc pendiente',
            "El jefe de {$papeleta->trabajador->nombre_completo} autorizó una salida fuera de tu horario. Requiere tu revisión.",
            fn () => $this->urlRrhh($papeleta),
        );
    }

    public function posthocObservada(Papeleta $papeleta): void
    {
        $jefe = $papeleta->resueltoPorJefe;

        if (! $jefe) {
            return;
        }

        $firme = $papeleta->revision_posthoc_estado === 'observada_firme';
        $nombre = $papeleta->trabajador->nombre_completo;

        $this->enviarUno(
            $jefe,
            $papeleta,
            $firme ? 'posthoc_observada_firme' : 'posthoc_observada',
            $firme ? 'Revisión post-hoc observada (definitiva)' : 'RRHH observó tu autorización',
            $firme
                ? "RRHH dejó un reparo definitivo sobre la papeleta de {$nombre} que autorizaste fuera de horario. Ya no admite respuesta y la papeleta pasó a Particular (con descuento)."
                : "RRHH observó la papeleta de {$nombre} que autorizaste fuera de su horario. Responde por escrito (y con un adjunto si quieres) para que vuelva a RRHH.",
            $this->urlJefe($papeleta),
        );
    }

    public function posthocRespondida(Papeleta $papeleta): void
    {
        $this->enviar(
            $this->usuariosRrhh(),
            $papeleta,
            'posthoc_respondida',
            'Respuesta a observación post-hoc',
            "El jefe respondió tu observación sobre la papeleta de {$papeleta->trabajador->nombre_completo}. Requiere tu revisión.",
            fn () => $this->urlRrhh($papeleta),
        );
    }

    public function rechazada(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'rechazada',
            'Papeleta rechazada',
            $papeleta->motivo_rechazo ?: 'Tu papeleta fue rechazada.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function observadaPorJefe(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'observada_jefe',
            'Papeleta observada',
            $papeleta->observacion_requiere_adjunto
                ? 'Tu jefe observó tu papeleta. Responde por escrito y adjunta el archivo que te pide para continuar.'
                : 'Tu jefe observó tu papeleta. Revisa el comentario y respóndelo por escrito para continuar.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function observacionRespondida(Papeleta $papeleta): void
    {
        $this->enviar(
            $this->destinatariosJefe($papeleta),
            $papeleta,
            'observacion_respondida',
            'Observación respondida',
            "{$papeleta->trabajador->nombre_completo} respondió tu observación. La papeleta volvió a tu bandeja.",
            fn () => $this->urlJefe($papeleta),
        );
    }

    public function observadaPorRrhh(Papeleta $papeleta): void
    {
        $this->enviar(
            $this->destinatariosJefe($papeleta),
            $papeleta,
            'observada_rrhh',
            'RRHH observó una papeleta de tu equipo',
            "RRHH observó la papeleta de {$papeleta->trabajador->nombre_completo}. Coordina la subsanación.",
            fn () => $this->urlJefe($papeleta),
        );
    }

    public function vencida(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'vencida',
            'Papeleta vencida',
            'Tu turno/día terminó sin que se decidiera tu papeleta. Quedó marcada como vencida.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function abandonoNoMarcado(Papeleta $papeleta): void
    {
        $destinatarios = $this->usuariosRrhh()->concat($this->destinatariosJefe($papeleta));

        $this->enviar(
            $destinatarios,
            $papeleta,
            'abandono_no_marcado',
            'Turno finalizado sin marcar retorno',
            "{$papeleta->trabajador->nombre_completo} no marcó su retorno antes de que terminara su turno/día.",
            fn (User $u) => $this->urlSegunRol($papeleta, $u),
        );
    }

    public function reclasificadaAParticular(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'reclasificada_particular',
            'Papeleta reclasificada a Particular',
            'El plazo para presentar sustento de Salud venció sin nada presentado. Tu papeleta se reclasificó a Particular.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function posthocAParticular(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'posthoc_a_particular',
            'Papeleta reclasificada a Particular',
            'RRHH no aprobó la revisión de tu papeleta autorizada fuera de horario. Pasó a Particular: se descuentan las horas que estuviste fuera.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function sustentoSinRevisar(Papeleta $papeleta): void
    {
        $destinatarios = $this->usuariosRrhh();

        $this->enviar(
            $destinatarios,
            $papeleta,
            'sustento_sin_revisar',
            'Sustento pendiente de revisión',
            "Venció el plazo del sustento de {$papeleta->trabajador->nombre_completo} y todavía no tiene decisión.",
            fn (User $u) => $this->urlSegunRol($papeleta, $u),
        );
    }

    public function sustentoObservado(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'sustento_observado',
            'Sustento observado',
            'El sustento que presentaste fue observado. Vuelve a subir un archivo antes de que venza el plazo.',
            $this->urlTrabajador($papeleta),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function usuariosRrhh(): Collection
    {
        return User::role('rrhh')->get();
    }

    private function destinatariosJefe(Papeleta $papeleta): Collection
    {
        return $papeleta->jefeInmediato ? collect([$papeleta->jefeInmediato]) : collect();
    }

    private function enviarUno(User $destinatario, Papeleta $papeleta, string $tipo, string $titulo, string $mensaje, ?string $url): void
    {
        Notification::send($destinatario, new PapeletaNotification($papeleta, $tipo, $titulo, $mensaje, $url));
    }

    /**
     * @param  Collection<int, User>  $destinatarios
     * @param  callable(User): ?string  $url
     */
    private function enviar(Collection $destinatarios, Papeleta $papeleta, string $tipo, string $titulo, string $mensaje, callable $url): void
    {
        foreach ($destinatarios->unique('id') as $destinatario) {
            $this->enviarUno($destinatario, $papeleta, $tipo, $titulo, $mensaje, $url($destinatario));
        }
    }

    private function urlSegunRol(Papeleta $papeleta, User $usuario): ?string
    {
        if ($usuario->hasRole('rrhh')) {
            return $this->urlRrhh($papeleta);
        }

        return $this->urlJefe($papeleta);
    }

    private function urlTrabajador(Papeleta $papeleta): ?string
    {
        return route('trabajador.papeletas.show', $papeleta);
    }

    private function urlJefe(Papeleta $papeleta): ?string
    {
        return route('jefe.papeletas.show', $papeleta);
    }

    private function urlRrhh(Papeleta $papeleta): ?string
    {
        return route('rrhh.papeletas.show', $papeleta);
    }
}
