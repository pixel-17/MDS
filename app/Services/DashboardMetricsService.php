<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\Retorno;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use App\States\Papeleta\Vencida;
use Illuminate\Support\Facades\Cache;

class DashboardMetricsService
{

    protected int $diasVentana = 30;

    public function __construct(
        private readonly ReporteHorasAcumuladasService $reporteHorasAcumuladas,
    ) {}

    public function paraAdmin(): array
    {
        return $this->recordar('admin', fn () => $this->calcularAdmin());
    }

    public function paraRrhh(User $rrhh): array
    {

        return $this->recordar('rrhh', fn () => $this->calcularRrhh($rrhh));
    }

    public function paraJefe(User $jefe): array
    {
        return $this->recordar('jefe:'.$jefe->id, fn () => $this->calcularJefe($jefe));
    }

    public static function invalidar(): void
    {
        Cache::add(self::CLAVE_VERSION, 1);
        Cache::increment(self::CLAVE_VERSION);
    }

    private const CLAVE_VERSION = 'dashboard-metricas:version';

    private const TTL_SEGUNDOS = 30;

    private function recordar(string $clave, \Closure $calcular): array
    {
        $version = (int) Cache::get(self::CLAVE_VERSION, 1);

        return Cache::remember("dashboard-metricas:{$version}:{$clave}", self::TTL_SEGUNDOS, $calcular);
    }

    private function calcularAdmin(): array
    {
        $desde = now()->subDays($this->diasVentana);

        $porEstado = Papeleta::whereNotState('estado', $this->estadosTerminales())
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'usuarios_total' => User::count(),
            'usuarios_por_rol' => [
                'admin' => User::role('admin')->count(),
                'rrhh' => User::role('rrhh')->count(),
                'trabajador' => User::role('trabajador')->count(),
            ],
            'sedes_activas' => Sede::where('activo', true)->count(),
            'unidades_organicas' => UnidadOrganica::where('activo', true)->count(),
            'papeletas_activas' => Papeleta::whereNotState('estado', $this->estadosTerminales())->count(),
            'papeletas_periodo' => Papeleta::where('created_at', '>=', $desde)->count(),
            'papeletas_vencidas' => $porEstado[Vencida::$name] ?? 0,
            'papeletas_pendiente_jefe' => $porEstado[PendienteJefe::$name] ?? 0,
            'papeletas_pendiente_rrhh' => ($porEstado[PendienteRrhh::$name] ?? 0) + ($porEstado[AutorizadaYCorriendo::$name] ?? 0),
            'retornos_manuales_periodo' => Retorno::where('marcado_manual', true)
                ->where('created_at', '>=', $desde)
                ->count(),
            'papeletas_por_sede' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->join('sedes', 'sedes.id', '=', 'papeletas.sede_id')
                ->selectRaw('sedes.nombre as etiqueta, count(*) as total')
                ->groupBy('sedes.nombre')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
        ];
    }

    private function calcularRrhh(User $rrhh): array
    {
        $desde = now()->subDays($this->diasVentana);

        $resueltasPeriodo = Papeleta::whereNotNull('rrhh_resuelto_at')
            ->where('rrhh_resuelto_at', '>=', $desde)
            ->get(['created_at', 'rrhh_resuelto_at']);

        $promedioMinutos = $resueltasPeriodo->isEmpty()
            ? null
            : (int) round($resueltasPeriodo->avg(
                fn (Papeleta $p) => $p->created_at->diffInMinutes($p->rrhh_resuelto_at)
            ));

        $afueraQuery = Papeleta::whereState('estado', AutorizadaYCorriendo::class);
        $trabajadoresAfueraTotal = (clone $afueraQuery)->count();
        $trabajadoresAfuera = $afueraQuery
            ->with(['trabajador', 'motivo'])
            ->orderBy('hora_salida_real')
            ->limit(8)
            ->get();

        $mesActual = now()->format('Y-m');
        $horasAcumuladasTop = $this->reporteHorasAcumuladas
            ->resumenPorTrabajador($rrhh, $mesActual)
            ->take(5);

        return [
            'pendientes_decision' => Papeleta::whereState('estado', PendienteRrhh::class)->count(),
            'posthoc_pendientes' => Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
                ->whereIn('revision_posthoc_estado', ['pendiente', 'respondida'])
                ->count(),
            'sustentos_por_revisar' => Papeleta::whereState('estado', EnJustificacion::class)
                ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
                ->count(),
            'promedio_resolucion_minutos' => $promedioMinutos,
            'papeletas_por_motivo' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->join('motivos', 'motivos.id', '=', 'papeletas.motivo_id')
                ->selectRaw('motivos.nombre as etiqueta, count(*) as total')
                ->groupBy('motivos.nombre')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'trabajadores_afuera' => $trabajadoresAfuera,
            'trabajadores_afuera_total' => $trabajadoresAfueraTotal,

            'papeletas_por_trabajador' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->join('users as trabajadores', 'trabajadores.id', '=', 'papeletas.trabajador_id')
                ->selectRaw("CONCAT(trabajadores.name, ' ', trabajadores.apellido) as etiqueta, count(*) as total")
                ->groupBy('trabajadores.id', 'trabajadores.name', 'trabajadores.apellido')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'horas_acumuladas_mes' => $mesActual,
            'horas_acumuladas_top' => $horasAcumuladasTop,

            'seguimiento_por_trabajador' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->where(function ($q) {
                    $q->whereState('estado', Rechazada::class)
                        ->orWhere('contador_observaciones_rrhh', '>=', 1);
                })
                ->join('users as trabajadores', 'trabajadores.id', '=', 'papeletas.trabajador_id')
                ->selectRaw("CONCAT(trabajadores.name, ' ', trabajadores.apellido) as etiqueta, count(*) as total")
                ->groupBy('trabajadores.id', 'trabajadores.name', 'trabajadores.apellido')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
        ];
    }

    private function calcularJefe(User $jefe): array
    {
        $desde = now()->subDays($this->diasVentana);

        $baseEquipo = fn () => Papeleta::deEquipoDe($jefe);

        $decididasPeriodo = $baseEquipo()
            ->where('created_at', '>=', $desde)
            ->whereState('estado', [Cerrada::class, Finalizada::class, EnJustificacion::class, Rechazada::class, AutorizadaYCorriendo::class, Vencida::class])
            ->get(['estado']);

        $tasaAprobacion = $decididasPeriodo->isEmpty()
            ? null
            : (int) round(
                $decididasPeriodo->filter(fn ($p) => ! ($p->estado instanceof Rechazada))->count()
                    / $decididasPeriodo->count() * 100
            );

        return [
            'equipo_total' => User::equipoDe($jefe)->count(),
            'por_decidir' => $baseEquipo()->whereState('estado', PendienteJefe::class)->count(),
            'observaciones_rrhh' => Papeleta::whereState('estado', ObservadaPorRrhh::class)
                ->deJefeInmediato($jefe)
                ->count(),
            'en_curso' => Papeleta::whereState('estado', AutorizadaYCorriendo::class)
                ->deJefeInmediato($jefe)
                ->count(),
            'sustentos_por_revisar' => Papeleta::whereState('estado', EnJustificacion::class)
                ->deJefeInmediato($jefe)
                ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
                ->count(),
            'tasa_aprobacion_periodo' => $tasaAprobacion,
        ];
    }

    protected function estadosTerminales(): array
    {
        return [
            Cerrada::class,
            Rechazada::class,
            Vencida::class,
            Cancelada::class,
            Finalizada::class,
        ];
    }
}
