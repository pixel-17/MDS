<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use Illuminate\Support\Facades\DB;

class RevisionPosthocAction
{
    use ExigeDecisorAjeno;

    private const REVISABLES = ['pendiente', 'respondida'];

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function aprobar(Papeleta $papeleta, User $rrhh): Papeleta
    {
        return $this->resolver($papeleta, $rrhh, 'aprobada');
    }

    public function observar(Papeleta $papeleta, User $rrhh, string $comentario): Papeleta
    {
        return $this->resolver($papeleta, $rrhh, 'observada', $comentario);
    }

    public function noAprobar(Papeleta $papeleta, User $rrhh, string $comentario): Papeleta
    {
        return $this->resolver($papeleta, $rrhh, 'no_aprobada', $comentario);
    }

    private function resolver(Papeleta $papeleta, User $rrhh, string $resultado, ?string $comentario = null): Papeleta
    {
        $reclasificada = false;

        $papeleta = DB::transaction(function () use ($papeleta, $rrhh, $resultado, $comentario, &$reclasificada) {

            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $rrhh);

            if (! $actual->autorizado_con_rrhh_fuera_horario) {
                throw new PapeletaException('Esta papeleta no requiere revisión post-hoc de RRHH.');
            }

            if (! in_array($actual->revision_posthoc_estado, self::REVISABLES, true)) {
                throw new PapeletaException('Esta papeleta ya tiene una revisión post-hoc registrada.');
            }

            $estadoNuevo = $resultado;

            if ($resultado === 'observada') {
                $tope = (int) Configuracion::valorDe('TOPE_OBSERVACIONES', 3);
                $actual->contador_observaciones_posthoc++;
                $actual->posthoc_observacion = $comentario;

                $sinQuienResponda = $actual->resuelto_por_jefe_id === null;

                if ($sinQuienResponda || $actual->contador_observaciones_posthoc >= $tope) {
                    $estadoNuevo = 'observada_firme';
                }
            }

            $estadoAntes = class_basename($actual->estado);
            $motivoAnteriorId = $actual->motivo_id;
            $reclasificada = false;

            if (in_array($estadoNuevo, ['no_aprobada', 'observada_firme'], true)) {
                $reclasificada = $this->pasarAParticular($actual);
            }

            $actual->revision_posthoc_estado = $estadoNuevo;
            $actual->revision_posthoc_por_id = $rrhh->id;
            $actual->revision_posthoc_at = now();
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => $estadoAntes,

                'estado_nuevo' => class_basename($actual->estado),
                'motivo_anterior_id' => $reclasificada ? $motivoAnteriorId : null,
                'motivo_nuevo_id' => $reclasificada ? $actual->motivo_id : null,
                'justificacion' => $comentario ?? 'Revisión post-hoc: autorización de jefe fuera de horario RRHH.',
            ]);

            return $actual;
        });

        if (in_array($papeleta->revision_posthoc_estado, ['observada', 'observada_firme'], true)) {
            $this->notificar->posthocObservada($papeleta);
        }

        if ($reclasificada) {
            $this->notificar->posthocAParticular($papeleta);
        }

        return $papeleta;
    }

    private function pasarAParticular(Papeleta $actual): bool
    {
        if ($actual->motivo->suma_descuento) {
            return false;
        }

        $particular = Motivo::where('es_destino_reclasificacion', true)->first();

        if (! $particular) {
            throw new PapeletaException('No hay un motivo configurado como destino de reclasificación (es_destino_reclasificacion).');
        }

        $actual->motivo_original_id = $actual->motivo_id;
        $actual->motivo_id = $particular->id;

        $actual->setRelation('motivo', $particular);
        $actual->requiere_visto_bueno = false;

        if ($actual->estado->equals(Cerrada::class)) {
            $actual->transicionarA(Finalizada::class);
        } elseif ($actual->estado->equals(EnJustificacion::class)) {

            $actual->sustentos()
                ->whereIn('estado', ['pendiente', 'presentado', 'observado'])
                ->update(['estado' => 'vencido']);

            $actual->transicionarA(Finalizada::class);
        }

        return true;
    }
}
