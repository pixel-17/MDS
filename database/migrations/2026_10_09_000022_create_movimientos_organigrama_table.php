<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_organigrama', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trabajador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('unidad_anterior_id')->nullable()->constrained('unidad_organicas')->nullOnDelete();
            $table->foreignId('unidad_nueva_id')->nullable()->constrained('unidad_organicas')->nullOnDelete();

            $table->foreignId('sede_anterior_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('sede_nueva_id')->nullable()->constrained('sedes')->nullOnDelete();

            $table->foreignId('jefe_inmediato_anterior_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('jefe_inmediato_nuevo_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('jefe_area_anterior_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('jefe_area_nuevo_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('revierte_id')->nullable()->constrained('movimientos_organigrama')->nullOnDelete();

            $table->timestamp('deshecho_at')->nullable();
            $table->foreignId('deshecho_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['trabajador_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_organigrama');
    }
};
