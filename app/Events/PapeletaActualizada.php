<?php

namespace App\Events;

use App\Models\Papeleta;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\PapeletaEstadoPresentacion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class PapeletaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    private const CANALES_POR_ENVIO = 50;

    private const CACHE_DESTINATARIOS_FIJOS = 'broadcast.destinatarios_fijos';

    private const TTL_DESTINATARIOS_FIJOS = 60;

    private static array $pendientes = [];

    /** @param  array<int, int>  $userIds */
    public function __construct(
        public int $papeletaId,
        public string $estado,
        public string $etiqueta,
        public array $userIds,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $id) => new PrivateChannel('App.Models.User.'.$id),
            $this->userIds
        );
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'papeleta_id' => $this->papeletaId,
            'estado' => $this->estado,
            'etiqueta' => $this->etiqueta,
        ];
    }

    public static function notificar(int $papeletaId): void
    {
        if (isset(self::$pendientes[$papeletaId])) {
            return;
        }

        self::$pendientes[$papeletaId] = true;

        DB::afterRollBack(function () use ($papeletaId) {
            unset(self::$pendientes[$papeletaId]);
        });

        DB::afterCommit(function () use ($papeletaId) {
            unset(self::$pendientes[$papeletaId]);

            try {

                try {
                    DashboardMetricsService::invalidar();
                } catch (Throwable $e) {
                    report($e);
                }

                if (app()->runningInConsole()) {
                    self::emitir($papeletaId);
                } else {
                    app()->terminating(fn () => self::emitir($papeletaId));
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    private static function emitir(int $papeletaId): void
    {
        try {
            $papeleta = Papeleta::find($papeletaId);

            if (! $papeleta) {
                return;
            }

            $ids = collect([$papeleta->trabajador_id, $papeleta->jefe_inmediato_id, $papeleta->jefe_area_id])
                ->merge(self::destinatariosFijos())
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            [$etiqueta] = ($papeleta->esAbandono() ? PapeletaEstadoPresentacion::porAbandono($papeleta->estado) : null)
                ?? PapeletaEstadoPresentacion::para($papeleta->estado);

            foreach ($ids->chunk(self::CANALES_POR_ENVIO) as $grupo) {
                event(new self($papeleta->id, class_basename($papeleta->estado), $etiqueta, $grupo->all()));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function destinatariosFijos(): array
    {
        return Cache::remember(
            self::CACHE_DESTINATARIOS_FIJOS,
            self::TTL_DESTINATARIOS_FIJOS,
            fn () => User::role(['rrhh', 'admin'])->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public static function olvidarDestinatariosFijos(): void
    {
        Cache::forget(self::CACHE_DESTINATARIOS_FIJOS);
    }
}
