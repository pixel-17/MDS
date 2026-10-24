<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papeletas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('en_revision_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('en_revision_desde')->nullable();

            $table->foreignId('trabajador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('motivo_id')->constrained('motivos');

            $table->foreignId('sede_id')->constrained('sedes');
            $table->enum('regimen', ['276', '728']);
            $table->date('dia_operativo');

            $table->dateTime('fin_turno_at')->nullable();

            //   pendiente_jefe, observada_por_jefe, pendiente_rrhh, observada_por_rrhh,
            //   autorizada_y_corriendo, en_justificacion, cerrada, finalizada,
            //   rechazada, vencida, cancelada
            $table->string('estado')->default('pendiente_jefe');

            $table->foreignId('jefe_inmediato_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por_jefe_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('jefe_resuelto_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_jefe')->default(0);

            $table->boolean('observacion_requiere_adjunto')->default(false);
            $table->string('observacion_adjunto_path')->nullable();
            $table->timestamp('observacion_subsanada_at')->nullable();
            $table->text('observacion_respuesta')->nullable();

            $table->timestamp('reloj_jefe_at')->nullable();

            $table->foreignId('jefe_area_id')->nullable()->constrained('users')->nullOnDelete();

            // --- RRHH ---
            $table->foreignId('resuelto_por_rrhh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rrhh_resuelto_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_rrhh')->default(0);
            $table->boolean('autorizado_con_rrhh_fuera_horario')->default(false);

            //   pendiente ──(RRHH aprueba)──► aprobada

            //   respondida ─(RRHH aprueba)──► aprobada | (RRHH observa)──► observada …

            $table->enum('revision_posthoc_estado', [
                'no_aplica',
                'pendiente',
                'observada',
                'respondida',
                'aprobada',
                'observada_firme',
                'no_aprobada',
            ])->default('no_aplica');
            $table->foreignId('revision_posthoc_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revision_posthoc_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_posthoc')->default(0);
            $table->text('posthoc_observacion')->nullable();
            $table->text('posthoc_respuesta')->nullable();
            $table->string('posthoc_adjunto_path')->nullable();
            $table->timestamp('posthoc_respondida_at')->nullable();

            $table->timestamp('hora_salida_real')->nullable();

            $table->unsignedSmallInteger('descuento_refrigerio_minutos')->default(0);

            $table->foreignId('rechazada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_rechazo')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->timestamp('vencida_at')->nullable();

            // texto: comision_servicio_campo | abandono_no_marcado | salud_no_justificada.
            $table->string('causa_finalizacion_sin_retorno', 40)->nullable();

            $table->boolean('requiere_visto_bueno')->default(false);
            $table->timestamp('regularizacion_fecha_limite')->nullable();

            $table->foreignId('motivo_original_id')->nullable()->constrained('motivos')->nullOnDelete();

            $table->text('justificacion')->nullable();

            $table->timestamp('hora_retorno_estimado')->nullable();

            $table->string('adjunto_inicial_path')->nullable();

            $table->timestamps();

            $table->index('trabajador_id');
            $table->index('dia_operativo');
            $table->index(['estado', 'dia_operativo']);
            $table->index(['estado', 'fin_turno_at']); // ProcesarAbandonoNoMarcado

            $table->index(['jefe_inmediato_id', 'estado'], 'papeletas_jefe_inmediato_estado_index');
            $table->index(['jefe_area_id', 'estado'], 'papeletas_jefe_area_estado_index');
            $table->index(['autorizado_con_rrhh_fuera_horario', 'revision_posthoc_estado'], 'papeletas_posthoc_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papeletas');
    }
};
