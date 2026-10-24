<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Database\Seeder;

class JefeInmediatoUserSeeder extends Seeder
{
    public function run(): void
    {
        $unidad = UnidadOrganica::where('nombre', 'Oficina de Recursos Humanos')->firstOrFail();

        $jefeInmediato = User::updateOrCreate(
            ['email' => 'jefeinmediato@mssc.test'],
            [
                'name' => 'Iván',
                'apellido' => 'Jefe Inmediato',
                'dni' => '10000004',
                'password' => 'password',
                'email_verified_at' => now(),
                'regimen' => '728',
                'unidad_organica_id' => $unidad->id,

                // cargarConfiguracion() rechaza trabajadores inactivos.
                'activo' => true,
            ]
        );

        if (! $jefeInmediato->hasRole('trabajador')) {
            $jefeInmediato->assignRole('trabajador');
        }

        $unidad->update(['jefe_id' => $jefeInmediato->id]);

        $this->cargarTurno($jefeInmediato);
    }

    private function cargarTurno(User $jefeInmediato): void
    {
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
            trabajador: $jefeInmediato,
            turno: $turno,
            fechaAncla: $fechaAncla,
            actor: $actor,
        );
    }
}
