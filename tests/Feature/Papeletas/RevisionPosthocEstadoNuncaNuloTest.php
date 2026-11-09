<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;


class RevisionPosthocEstadoNuncaNuloTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    
    private function topeDePrueba(): User
    {
        $tope = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $tope->id]);
        $tope->update(['unidad_organica_id' => $unidad->id]);
        $this->turnoDePrueba($tope);

        
        
        $this->conUnidadHija($unidad);

        return $tope->fresh();
    }

    public function test_pendiente_jefe_nunca_guarda_null(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($trabajador); 
        $this->turnoDePrueba($jefe);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
        $this->assertSame('no_aplica', $papeleta->fresh()->revision_posthoc_estado);
    }

    public function test_pendiente_rrhh_nunca_guarda_null(): void
    {
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00')); 

        $tope = $this->topeDePrueba();

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($tope, $this->motivoDe('PARTICULAR'), []);

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteRrhh::class));
        $this->assertSame('no_aplica', $papeleta->fresh()->revision_posthoc_estado);
    }

    public function test_autorizada_y_corriendo_guarda_pendiente_nunca_null(): void
    {
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->travelTo(Carbon::parse('2026-09-21 22:00:00')); 

        $tope = $this->topeDePrueba();

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($tope, $this->motivoDe('PARTICULAR'), []);

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
        $this->assertSame('pendiente', $papeleta->fresh()->revision_posthoc_estado);
    }
}
