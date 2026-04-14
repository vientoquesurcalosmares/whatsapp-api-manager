<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea tabla de configuración del Data API endpoint por flow.
     * Un flow tiene como máximo un endpoint config (UNIQUE en flow_id).
     * Soporta 3 modos: auto (no-code), webhook (proxy), class (custom handler).
     */
    public function up(): void
    {
        Schema::create('whatsapp_flow_endpoint_configs', function (Blueprint $table) {
            $table->ulid('flow_endpoint_config_id')->primary();

            $table->foreignUlid('flow_id')
                  ->unique()
                  ->constrained('whatsapp_flows', 'flow_id')
                  ->onDelete('cascade');

            $table->boolean('is_enabled')->default(false);
            $table->enum('mode', ['auto', 'webhook', 'class'])->default('auto');

            // Modo webhook
            $table->string('webhook_url', 500)->nullable();
            $table->unsignedInteger('webhook_timeout_ms')->default(6000)
                  ->comment('Timeout en ms, máx 8000 por límite Meta');
            $table->string('webhook_secret', 255)->nullable();

            // Modo class
            $table->string('handler_class', 255)->nullable();

            // Modo auto
            $table->json('auto_config')->nullable()
                  ->comment('{"first_screen":"ID","init_data":{},"screen_transitions":{"S1":"S2","S2":null}}');

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_endpoint_configs');
    }
};
