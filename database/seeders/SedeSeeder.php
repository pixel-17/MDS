<?php

namespace Database\Seeders;

use App\Models\Sede;
use Illuminate\Database\Seeder;

class SedeSeeder extends Seeder
{
    public function run(): void
    {
        Sede::updateOrCreate(
            ['nombre' => 'Municipalidad Distrital de Santiago (Cusco)'],
            [
                'direccion' => 'Distrito de Santiago, Cusco, Perú',
                'latitud' => -13.5361000,
                'longitud' => -71.9629000,
                'radio_metros' => 150,
                'activo' => true,
            ]
        );
    }
}
