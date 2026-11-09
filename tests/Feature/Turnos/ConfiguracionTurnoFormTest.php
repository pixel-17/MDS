<?php

namespace Tests\Feature\Turnos;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Livewire\Turnos\ConfiguracionTurnoForm;
use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;


class ConfiguracionTurnoFormTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_avisa_cuando_el_trabajador_276_no_tiene_turno_vigente_hoy(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            ->assertSet('sinTurnoVigenteHoy', true)
            ->assertSee('no tiene un turno vigente cargado para hoy');
    }

    public function test_no_avisa_cuando_el_trabajador_ya_tiene_turno_vigente_hoy(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            ->assertSet('sinTurnoVigenteHoy', false)
            ->assertDontSee('no tiene un turno vigente cargado para hoy');
    }

    
    public function test_guardar_genera_el_mes_y_pinta_el_calendario_segun_el_horario_ordinario(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSeeInOrder(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']);

        $config = ConfiguracionTurno::where('user_id', $trabajador->id)->first();
        $this->assertNotNull($config);
        $this->assertSame(5, $config->dias_trabajo);
        $this->assertSame(2, $config->dias_descanso);

        $turnosDelMes = Turno::where('user_id', $trabajador->id)
            ->whereBetween('fecha', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->get();

        $sabadosYDomingos = $turnosDelMes->filter(fn (Turno $turno) => $turno->fecha->isWeekend());

        $this->assertNotEmpty($sabadosYDomingos);
        $this->assertTrue($sabadosYDomingos->every(fn (Turno $turno) => $turno->es_descanso));
        $this->assertTrue(
            $turnosDelMes->diff($sabadosYDomingos)->every(fn (Turno $turno) => ! $turno->es_descanso)
        );
    }

    public function test_guardar_la_configuracion_quita_el_bloqueo_para_crear_papeletas(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            ->call('guardar')
            ->assertHasNoErrors();

        $papeleta = app(CrearPapeletaAction::class)
            ->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertNotNull($papeleta->id);
    }
}