<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('sustentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('papeleta_id')->constrained('papeletas')->cascadeOnDelete();

            $table->string('archivo_path')->nullable();
            $table->timestamp('fecha_limite');
            $table->enum('estado', ['pendiente', 'presentado', 'vencido', 'aprobado', 'observado'])
                ->default('pendiente');

            $table->timestamp('presentado_at')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();

            $table->timestamps();

            $table->index(['estado', 'fecha_limite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sustentos');
    }
};
