<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrabajadorUserSeeder extends Seeder
{
    public function run(): void
    {
        $unidad = UnidadOrganica::where('nombre', 'Oficina de Recursos Humanos')->firstOrFail();

        $jefeInmediato = User::where('email', 'jefeinmediato@mssc.test')->firstOrFail();
        $jefeArea = User::where('email', 'jefearea@mssc.test')->firstOrFail();

        $trabajador = User::updateOrCreate(
            ['email' => 'trabajador@mssc.test'],
            [
                'name' => 'Tomás',
                'apellido' => 'Trabajador',
                'dni' => '10000005',
                'password' => 'password',
                'email_verified_at' => now(),
                'regimen' => '276',
                'unidad_organica_id' => $unidad->id,
                'jefe_inmediato_id' => $jefeInmediato->id,
                'jefe_area_id' => $jefeArea->id,
            ]
        );

        if (! $trabajador->hasRole('trabajador')) {
            $trabajador->assignRole('trabajador');
        }
    }
}
