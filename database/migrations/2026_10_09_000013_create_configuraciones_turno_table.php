<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('configuraciones_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('turno');
            $table->date('fecha_ancla');
            $table->unsignedTinyInteger('dias_trabajo')->default(6);
            $table->unsignedTinyInteger('dias_descanso')->default(1);
            $table->foreignId('actualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_turno');
    }
};
