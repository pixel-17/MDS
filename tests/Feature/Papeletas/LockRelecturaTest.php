<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\ReconocerObservacionRrhhAction;
use App\Actions\Papeleta\RevisarSustentoAction;
use App\Actions\Papeleta\RevisionPosthocAction;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\ObservadaPorRrhh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;


class LockRelecturaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $trabajador;

    private User $jefe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->jefe = $this->usuarioDePrueba();
        $this->trabajador = $this->usuarioDePrueba(['jefe_inmediato_id' => $this->jefe->id]);
    }

    
    private function cambiarEnBd(Papeleta $papeleta, array $columnas): void
    {
        DB::table('papeletas')->where('id', $papeleta->id)->update($columnas);
    }

    public function test_reconocer_observacion_no_reabre_una_papeleta_que_ya_avanzo(): void
    {
        $obsoleta = $this->papeletaDePrueba($this->trabajador, ObservadaPorRrhh::class);
        $this->cambiarEnBd($obsoleta, ['estado' => 'pendiente_jefe']);

        $this->assertThrows(
            fn () => app(ReconocerObservacionRrhhAction::class)->ejecutar($obsoleta, $this->jefe, 'Coordinado'),
            PapeletaException::class,
            'Esta papeleta no tiene una observación de RRHH pendiente de reconocer.',
        );
    }

    public function test_revision_posthoc_no_se_registra_dos_veces(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $obsoleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class, [
            'autorizado_con_rrhh_fuera_horario' => true,
            'revision_posthoc_estado' => 'pendiente',
        ]);
        $this->cambiarEnBd($obsoleta, ['revision_posthoc_estado' => 'aprobada']);

        $this->assertThrows(
            fn () => app(RevisionPosthocAction::class)->observar($obsoleta, $rrhh, 'Fuera de norma'),
            PapeletaException::class,
            'Esta papeleta ya tiene una revisión post-hoc registrada.',
        );

        $this->assertSame('aprobada', $obsoleta->fresh()->revision_posthoc_estado);
    }

    public function test_revisar_sustento_rechaza_si_la_papeleta_ya_no_espera_sustento(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, EnJustificacion::class);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'archivo_path' => 'sustentos/prueba.pdf',
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
        ]);
        $sustento->load('papeleta'); 

        $this->cambiarEnBd($papeleta, ['estado' => 'cerrada']);

        $this->assertThrows(
            fn () => app(RevisarSustentoAction::class)->aprobar($sustento, $this->jefe),
            PapeletaException::class,
            'Esta papeleta ya no está en justificación.',
        );

        $this->assertSame('presentado', $sustento->fresh()->estado, 'no debe quedar escritura a medias');
    }

    public function test_revisar_sustento_aprobado_cierra_la_papeleta(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, EnJustificacion::class);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'archivo_path' => 'sustentos/prueba.pdf',
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
        ]);

        app(RevisarSustentoAction::class)->aprobar($sustento, $this->jefe);

        $this->assertTrue($papeleta->fresh()->estado->equals(Cerrada::class));
        $this->assertSame('aprobado', $sustento->fresh()->estado);
        $this->assertSame($this->jefe->id, $sustento->fresh()->revisado_por_id);
    }

    public function test_un_revisor_no_puede_revisar_el_sustento_de_su_propia_papeleta(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh', 'trabajador']);
        $papeleta = $this->papeletaDePrueba($rrhh, EnJustificacion::class);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'archivo_path' => 'sustentos/prueba.pdf',
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
        ]);

        $this->assertThrows(
            fn () => app(RevisarSustentoAction::class)->aprobar($sustento, $rrhh),
            PapeletaException::class,
            'No puedes decidir, revisar ni cerrar tu propia papeleta.',
        );

        $this->assertSame('presentado', $sustento->fresh()->estado);
    }
}
