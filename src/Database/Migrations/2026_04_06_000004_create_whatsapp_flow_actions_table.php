<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea tabla de acciones configurables que se ejecutan tras triggers de un flow.
     * Tipos: webhook_post, email_notification, internal_event.
     * Triggers: on_complete, on_screen.
     * El campo execution_order controla el orden de ejecución dentro del mismo trigger.
     */
    public function up(): void
    {
        Schema::create('whatsapp_flow_actions', function (Blueprint $table) {
            $table->ulid('flow_action_id')->primary();

            $table->foreignUlid('flow_id')
                  ->constrained('whatsapp_flows', 'flow_id')
                  ->onDelete('cascade');

            $table->string('name', 255);
            $table->enum('action_type', ['webhook_post', 'email_notification', 'internal_event']);
            $table->enum('trigger', ['on_complete', 'on_screen'])->default('on_complete');
            $table->string('trigger_screen', 100)->nullable()
                  ->comment('Para trigger=on_screen: qué pantalla dispara la acción');

            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('execution_order')->default(0);

            $table->json('config')->nullable()
                  ->comment('Config por tipo: {url,method,headers} / {to,subject,template} / {event_class,payload_mapping}');
            $table->json('retry_config')->nullable()
                  ->comment('{"attempts":3,"backoff":[10,30,60]}');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('flow_id');
            $table->index(['flow_id', 'trigger']);
            $table->index('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_actions');
    }
};
