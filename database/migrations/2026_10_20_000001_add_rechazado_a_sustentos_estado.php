<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {

            return;
        }

        DB::statement("ALTER TABLE sustentos MODIFY estado ENUM('pendiente', 'presentado', 'vencido', 'aprobado', 'observado', 'rechazado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE sustentos MODIFY estado ENUM('pendiente', 'presentado', 'vencido', 'aprobado', 'observado') NOT NULL DEFAULT 'pendiente'");
    }
};
