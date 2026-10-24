<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('unidad_organicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');

            $table->enum('tipo', [
                'alta_direccion',
                'consultivo',
                'control',
                'apoyo',
                'apoyo_alcaldia',
                'asesoramiento',
                'linea_2do_nivel',
                'linea_3er_nivel',
            ])->nullable();

            $table->foreignId('parent_id')->nullable()
                ->constrained('unidad_organicas')->restrictOnDelete();

            $table->foreignId('jefe_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unidad_organica_id')->nullable()->after('jefe_area_id')
                ->constrained('unidad_organicas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_organica_id');
        });

        Schema::dropIfExists('unidad_organicas');
    }
};
