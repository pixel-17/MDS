<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Database\Seeder;

class JefeAreaUserSeeder extends Seeder
{
    public function run(): void
    {
        $unidad = UnidadOrganica::where('nombre', 'Oficina General de Administración y Finanzas')->firstOrFail();

        $jefeArea = User::updateOrCreate(
            ['email' => 'jefearea@mssc.test'],
            [
                'name' => 'Jorge',
                'apellido' => 'Jefe de Área',
                'dni' => '10000003',
                'password' => 'password',
                'email_verified_at' => now(),
                'regimen' => '728',
                'unidad_organica_id' => $unidad->id,
                'activo' => true,
            ]
        );

        if (! $jefeArea->hasRole('trabajador')) {
            $jefeArea->assignRole('trabajador');
        }

        $unidad->update(['jefe_id' => $jefeArea->id]);

        $jefeArea->refresh();

        $ahora = now();
        $hora = (int) $ahora->format('G');
        $turno = match (true) {
            $hora >= 6 && $hora < 14 => 'MANANA',
            $hora >= 14 && $hora < 22 => 'TARDE',
            default => 'NOCHE',
        };
        $fechaAncla = $hora < 6 ? $ahora->copy()->subDay() : $ahora->copy();
        $actor = User::where('email', 'admin@mssc.test')->firstOrFail();

        app(GeneradorTurnoMensualService::class)->cargarConfiguracion(
            trabajador: $jefeArea,
            turno: $turno,
            fechaAncla: $fechaAncla,
            actor: $actor,
        );
    }
}
