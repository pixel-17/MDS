<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Support\Facades\DB;

class AprobarRrhhAction
{
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $rrhh): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $rrhh) {

            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $rrhh);

            if (! $actual->estado->equals(PendienteRrhh::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
            }

            $actual->transicionarConHistorial(
                AutorizadaYCorriendo::class,
                $rrhh->id,
                'rrhh',
                cambios: function (Papeleta $p) use ($rrhh) {
                    $p->resuelto_por_rrhh_id = $rrhh->id;
                    $p->rrhh_resuelto_at = now();
                    $p->hora_salida_real = now();
                },
            );

            return $actual;
        });

        $this->notificar->puedeSalir($papeleta);

        return $papeleta;
    }
}
