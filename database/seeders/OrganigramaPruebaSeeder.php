<?php

namespace Database\Seeders;

use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class OrganigramaPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $rolTrabajador = Role::where('name', 'trabajador')->firstOrFail();
        $sede = Sede::where('activo', true)->orderBy('id')->first();

        $unidades = UnidadOrganica::whereNotNull('parent_id')
            ->whereNull('jefe_id')
            ->orderBy('id')
            ->get();

        $contador = 20000000;

        foreach ($unidades as $unidad) {
            $contador++;

            $jefe = User::create([
                'name' => 'Jefe',
                'apellido' => mb_substr($unidad->nombre, 0, 250),
                'dni' => (string) $contador,
                'email' => "jefe{$contador}@mssc.test",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'regimen' => '276',
                'sede_id' => $sede?->id,
                'unidad_organica_id' => $unidad->id,
                'activo' => true,
            ]);

            $jefe->assignRole($rolTrabajador);

            $unidad->update(['jefe_id' => $jefe->id]);
        }

        if ($sede) {
            User::whereNull('sede_id')
                ->whereNotNull('unidad_organica_id')
                ->update(['sede_id' => $sede->id]);
        }

        Artisan::call('jefaturas:recalcular');
    }
}
