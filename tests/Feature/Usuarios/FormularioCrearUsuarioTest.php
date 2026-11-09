<?php

namespace Tests\Feature\Usuarios;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;


class FormularioCrearUsuarioTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefeDeArea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        
        
        $this->jefeDeArea = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->jefeDeArea->id]);
        $this->conUnidadHija($unidad);
    }

    public function test_el_formulario_muestra_la_sede_que_heredara_el_trabajador(): void
    {
        $this->actingAs($this->jefeDeArea)
            ->get(route('usuarios.crear'))
            ->assertOk()
            ->assertSee('Sede del trabajador')
            
            ->assertSee('Sede central');
    }

    public function test_el_alta_no_pide_turno_porque_el_trabajador_lo_programa_despues(): void
    {
        $this->actingAs($this->jefeDeArea)
            ->get(route('usuarios.crear'))
            ->assertOk()
            ->assertDontSee('name="turno"', false)
            ->assertSee('se carga después', false);
    }
}
