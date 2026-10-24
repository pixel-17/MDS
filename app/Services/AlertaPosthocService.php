<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Papeleta;
use App\Models\User;
use App\Notifications\AlertaOrganizacionalNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class AlertaPosthocService
{
    public const CANTIDAD_DEFECTO = 10;

    public const HORAS_DEFECTO = 24;

    public function evaluar(): bool
    {
        $total = $this->pendientes()->count();

        if ($total === 0) {
            return false;
        }

        $umbralCantidad = max(1, (int) Configuracion::valorDe('POSTHOC_ALERTA_CANTIDAD', self::CANTIDAD_DEFECTO));
        $umbralHoras = max(1, (int) Configuracion::valorDe('POSTHOC_ALERTA_HORAS', self::HORAS_DEFECTO));

        $horasDeLaMasAntigua = $this->horasDeLaMasAntigua();

        $porCantidad = $total >= $umbralCantidad;
        $porAntiguedad = $horasDeLaMasAntigua >= $umbralHoras;

        if (! $porCantidad && ! $porAntiguedad) {
            return false;
        }

        if (! Cache::add('alerta-posthoc:'.now()->toDateString(), true, now()->endOfDay())) {
            return false;
        }

        $this->avisar($total, $horasDeLaMasAntigua);

        return true;
    }

    private function pendientes(): Builder
    {
        return Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
            ->whereIn('revision_posthoc_estado', ['pendiente', 'respondida']);
    }

    private function horasDeLaMasAntigua(): int
    {
        $masAntigua = $this->pendientes()
            ->selectRaw('MIN(COALESCE(hora_salida_real, created_at)) as desde')
            ->value('desde');

        if ($masAntigua === null) {
            return 0;
        }

        return (int) Carbon::parse($masAntigua)->diffInHours(now(), true);
    }

    private function avisar(int $total, int $horasDeLaMasAntigua): void
    {
        $titulo = 'Revisiones post-hoc acumuladas';
        $mensaje = $total === 1
            ? "Hay 1 revisión post-hoc pendiente; lleva {$horasDeLaMasAntigua} h sin revisarse."
            : "Hay {$total} revisiones post-hoc pendientes; la más antigua lleva {$horasDeLaMasAntigua} h sin revisarse.";

        $rrhh = User::role('rrhh')->where('activo', true)->get();

        $admins = User::role('admin')
            ->where('activo', true)
            ->whereNotIn('id', $rrhh->modelKeys())
            ->get();

        if ($rrhh->isNotEmpty()) {
            Notification::send(
                $rrhh,
                new AlertaOrganizacionalNotification('posthoc_acumulado', $titulo, $mensaje, route('rrhh.papeletas.index'))
            );
        }

        if ($admins->isNotEmpty()) {
            Notification::send(
                $admins,
                new AlertaOrganizacionalNotification('posthoc_acumulado', $titulo, $mensaje)
            );
        }
    }
}
