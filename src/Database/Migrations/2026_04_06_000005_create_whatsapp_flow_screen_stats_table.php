<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea tabla de estadísticas diarias por pantalla de flow.
     * PK es bigint autoincrement (tabla de stats — no necesita ULID).
     * UNIQUE KEY en [flow_id, phone_number_id, screen_name, stat_date] permite upsert atómico.
     * Sin softDeletes — los stats no se eliminan lógicamente.
     */
    public function up(): void
    {
        Schema::create('whatsapp_flow_screen_stats', function (Blueprint $table) {
            $table->bigIncrements('stat_id');

            $table->foreignUlid('flow_id')
                  ->constrained('whatsapp_flows', 'flow_id')
                  ->onDelete('cascade');

            $table->ulid('phone_number_id')->nullable();
            $table->foreign('phone_number_id')
                  ->references('phone_number_id')
                  ->on('whatsapp_phone_numbers')
                  ->onDelete('set null');

            $table->ulid('screen_id')->nullable();
            $table->foreign('screen_id')
                  ->references('screen_id')
                  ->on('whatsapp_flow_screens')
                  ->onDelete('set null');

            $table->string('screen_name', 100);
            $table->date('stat_date');

            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('completions_count')->default(0);
            $table->unsignedInteger('drop_off_count')->default(0);
            $table->unsignedInteger('avg_time_on_screen_ms')->nullable();

            $table->timestamps();

            // UNIQUE para upsert atómico correcto
            $table->unique(['flow_id', 'phone_number_id', 'screen_name', 'stat_date']);

            // Índices adicionales para queries de analytics
            $table->index('flow_id');
            $table->index('phone_number_id');
            $table->index('stat_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_screen_stats');
    }
};
