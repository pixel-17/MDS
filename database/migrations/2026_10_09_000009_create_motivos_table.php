<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('motivos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // PARTICULAR | SALUD | COMISION | EMERGENCIA
            $table->string('nombre');

            $table->enum('adjunto', ['no', 'opcional', 'flexible', 'obligatorio'])->default('no');

            $table->boolean('suma_descuento')->default(false);
            $table->boolean('requiere_sustento_en_retorno')->default(false); // Salud

            $table->unsignedSmallInteger('plazo_justificacion_horas_habiles')->nullable();

            $table->boolean('es_destino_reclasificacion')->default(false);

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos');
    }
};
