<?php

namespace App\Services;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Notifications\AlertaOrganizacionalNotification;
use Illuminate\Support\Facades\Notification;

class AlertaJefaturaService
{

    public function avisarFaltantesEnTodoElOrganigrama(): int
    {
        $avisos = 0;

        $unidades = UnidadOrganica::where('activo', true)
            ->with(['jefe', 'padre.jefe'])
            ->where(fn ($q) => $q
                ->whereHas('miembros', fn ($m) => $m->where('activo', true))
                ->orWhereHas('hijos', fn ($h) => $h->where('activo', true)))
            ->get();

        foreach ($unidades as $unidad) {
            if (! $unidad->jefe?->activo) {
                $this->avisar($unidad);
                $avisos++;
            }
        }

        return $avisos;
    }

    private function avisar(UnidadOrganica $unidad): void
    {
        $destinatarios = User::role('admin')->get();

        $jefeArea = $unidad->jefeArea();

        if ($jefeArea?->activo) {
            $destinatarios->push($jefeArea);
        }

        Notification::send(
            $destinatarios->unique('id'),
            new AlertaOrganizacionalNotification(
                'jefatura_faltante',
                'Unidad sin jefe',
                "La unidad \"{$unidad->nombre}\" no tiene un jefe activo asignado.",
            )
        );
    }
}
