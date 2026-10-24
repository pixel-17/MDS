<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('historial_papeletas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('papeleta_id')->constrained('papeletas')->cascadeOnDelete();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('actor_tipo', ['trabajador', 'jefe_inmediato', 'jefe_area', 'rrhh', 'sistema']);

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('motivo_anterior_id')->nullable()->constrained('motivos')->nullOnDelete();
            $table->foreignId('motivo_nuevo_id')->nullable()->constrained('motivos')->nullOnDelete();

            $table->text('justificacion')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_papeletas');
    }
};
