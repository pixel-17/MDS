<?php

namespace App\Console\Commands;

use App\Models\Papeleta;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Console\Command;

class RecalcularJefaturas extends Command
{
    protected $signature = 'jefaturas:recalcular
        {--dry-run : Muestra qué cambiaría, sin guardar nada}
        {--papeletas-abiertas : Corrige también la fotografía de las papeletas no terminales con jefe = el propio trabajador}';

    protected $description = 'Recalcula jefe inmediato y jefe de área de los usuarios según el organigrama vigente.';

    public function handle(UserObserver $observer): int
    {
        $simulacion = (bool) $this->option('dry-run');
        $usuariosCambiados = 0;
        $arbol = UnidadOrganica::arbolEnMemoria();

        User::whereNotNull('unidad_organica_id')
            ->chunkById(200, function ($usuarios) use ($observer, $arbol, $simulacion, &$usuariosCambiados) {
                foreach ($usuarios as $usuario) {
                    $observer->resincronizar($usuario, $arbol->get($usuario->unidad_organica_id));

                    if (! $usuario->isDirty(['jefe_inmediato_id', 'jefe_area_id'])) {
                        continue;
                    }

                    $usuariosCambiados++;

                    $this->line(sprintf(
                        '  usuario #%d: jefe inmediato %s -> %s | jefe de área %s -> %s',
                        $usuario->id,
                        $usuario->getOriginal('jefe_inmediato_id') ?? 'null',
                        $usuario->jefe_inmediato_id ?? 'null',
                        $usuario->getOriginal('jefe_area_id') ?? 'null',
                        $usuario->jefe_area_id ?? 'null',
                    ));

                    if (! $simulacion) {
                        $usuario->saveQuietly();
                    }
                }
            });

        $this->info(($simulacion ? '[dry-run] ' : '')."Usuarios con jefaturas corregidas: {$usuariosCambiados}.");

        if ($this->option('papeletas-abiertas')) {
            $this->corregirPapeletasAbiertas($simulacion);
        }

        return self::SUCCESS;
    }

    private function corregirPapeletasAbiertas(bool $simulacion): void
    {
        $corregidas = 0;

        Papeleta::whereColumn('jefe_inmediato_id', 'trabajador_id')
            ->with('trabajador')
            ->chunkById(200, function ($papeletas) use ($simulacion, &$corregidas) {
                foreach ($papeletas as $papeleta) {
                    if ($papeleta->estado->esTerminal()) {
                        continue;
                    }

                    $corregidas++;

                    $this->line("  papeleta #{$papeleta->id}: jefe inmediato -> {$papeleta->trabajador->jefe_inmediato_id}");

                    if (! $simulacion) {
                        $papeleta->jefe_inmediato_id = $papeleta->trabajador->jefe_inmediato_id;
                        $papeleta->jefe_area_id = $papeleta->trabajador->jefe_area_id;
                        $papeleta->saveQuietly();
                    }
                }
            });

        $this->info(($simulacion ? '[dry-run] ' : '')."Papeletas abiertas corregidas: {$corregidas}.");
    }
}
