<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('retornos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('papeleta_id')->unique()->constrained('papeletas')->cascadeOnDelete();

            $table->string('foto_path')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->boolean('dentro_de_radio')->nullable();

            $table->timestamp('hora_servidor')->nullable();

            $table->boolean('marcado_manual')->default(false);

            $table->boolean('generado_por_abandono')->default(false);

            $table->foreignId('marcado_manual_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justificacion_manual')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retornos');
    }
};
