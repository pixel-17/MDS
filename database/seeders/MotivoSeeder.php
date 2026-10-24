<?php

namespace Database\Seeders;

use App\Models\Motivo;
use Illuminate\Database\Seeder;

class MotivoSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            [
                'codigo' => 'PARTICULAR',
                'nombre' => 'Particular',
                'adjunto' => 'no',
                'suma_descuento' => true,
                'requiere_sustento_en_retorno' => false,
                'es_destino_reclasificacion' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'SALUD',
                'nombre' => 'Salud',
                'adjunto' => 'no',
                'suma_descuento' => false,
                'requiere_sustento_en_retorno' => true,
                'es_destino_reclasificacion' => false,
                'activo' => true,
            ],
            [
                'codigo' => 'COMISION',
                'nombre' => 'Comisión de Servicio',
                'adjunto' => 'no',
                'suma_descuento' => false,
                'requiere_sustento_en_retorno' => false,
                'es_destino_reclasificacion' => false,
                'activo' => true,
            ],
        ];

        foreach ($motivos as $motivo) {
            Motivo::updateOrCreate(['codigo' => $motivo['codigo']], $motivo);
        }
    }
}
