<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Seeder;

class RrhhUserSeeder extends Seeder
{
    public function run(): void
    {
        $areaRrhh = UnidadOrganica::where('nombre', 'Oficina de Recursos Humanos')->firstOrFail();

        $rrhh = User::updateOrCreate(
            ['email' => 'rrhh@mssc.test'],
            [
                'name' => 'Rosa',
                'apellido' => 'Recursos Humanos',
                'dni' => '10000002',
                'password' => 'password',
                'email_verified_at' => now(),
                'regimen' => '276',
                'unidad_organica_id' => $areaRrhh->id,
            ]
        );

        if (! $rrhh->hasRole('rrhh')) {
            $rrhh->assignRole('rrhh');
        }
    }
}
