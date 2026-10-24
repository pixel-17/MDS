<?php

namespace App\Actions\Organigrama;

use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Support\Facades\DB;
use Throwable;

class PapeletasAbiertasService
{

    private const ESTADOS_DEL_JEFE = [PendienteJefe::class, ObservadaPorJefe::class];

    public function contarPendientesDeJefe(User $jefe): int
    {
        return Papeleta::whereState('estado', self::ESTADOS_DEL_JEFE)
            ->where('jefe_inmediato_id', $jefe->id)
            ->count();
    }

    public function reasignarDeTrabajador(User $trabajador): int
    {
        $jefeId = $trabajador->jefe_inmediato_id;

        if ($jefeId === null) {
            return 0;
        }

        $papeletas = Papeleta::whereState('estado', self::ESTADOS_DEL_JEFE)
            ->where('trabajador_id', $trabajador->id)
            ->where(fn ($q) => $q
                ->where('jefe_inmediato_id', '!=', $jefeId)
                ->orWhereNull('jefe_inmediato_id'))
            ->get();

        foreach ($papeletas as $papeleta) {
            DB::transaction(function () use ($papeleta, $trabajador, $jefeId) {
                $papeleta->forceFill([
                    'jefe_inmediato_id' => $jefeId,
                    'jefe_area_id' => $trabajador->jefe_area_id,
                ])->save();

                HistorialPapeleta::create([
                    'papeleta_id' => $papeleta->id,
                    'actor_id' => null,
                    'actor_tipo' => 'sistema',
                    'estado_nuevo' => class_basename($papeleta->estado),
                    'justificacion' => 'Cambió el jefe inmediato: la papeleta pasa al jefe actual.',
                ]);
            });

            try {
                app(NotificarPapeletaService::class)->creada($papeleta->fresh());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $papeletas->count();
    }
}
