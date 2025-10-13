<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_general_template_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('wa_template_id', 200);
            $table->string('granularity', 50)->default('DAILY')->comment('Solo DAILY permitido por la API actual');
            $table->string('product_type', 50)->default('cloud_api')->comment('cloud_api, on_premise, etc.');
            $table->bigInteger('start_timestamp')->comment('Unix timestamp de inicio del período');
            $table->bigInteger('end_timestamp')->comment('Unix timestamp de fin del período');
            $table->date('start_date')->comment('Fecha de inicio calculada desde timestamp');
            $table->date('end_date')->comment('Fecha de fin calculada desde timestamp');
            $table->integer('sent')->default(0)->comment('Mensajes enviados');
            $table->integer('delivered')->default(0)->comment('Mensajes entregados');
            $table->integer('read')->default(0)->comment('Mensajes leídos');
            $table->json('json_data')->nullable()->comment('JSON completo del data_point desde la API de WhatsApp');
            $table->timestamps();
            $table->softDeletes();

            // Índices para optimización de consultas
            $table->index('wa_template_id', 'idx_w_g_ta_template_id');
            $table->index(['wa_template_id', 'start_date', 'end_date'], 'idx_w_g_ta_template_date_range');
            $table->index(['start_date', 'end_date'], 'idx_w_g_ta_date_range');
            $table->index('granularity', 'idx_w_g_ta_granularity');
            $table->unique(['wa_template_id', 'start_timestamp', 'end_timestamp'], 'unique_w_g_ta_template_period');

            // Relación con whatsapp_templates
            $table->foreign('wa_template_id')
                ->references('wa_template_id')
                ->on('whatsapp_templates')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_general_template_analytics');
    }
};