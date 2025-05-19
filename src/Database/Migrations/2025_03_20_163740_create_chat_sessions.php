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
        Schema::create('whatsapp_chat_sessions', function (Blueprint $table) {
            $table->ulid('session_id')->primary();
            $table->ulid('contact_id');
            $table->ulid('whatsapp_phone_id'); // Número de WhatsApp usado
            $table->ulid('assigned_bot_id')->nullable();
            $table->foreignUlid('flow_id')->nullable()->constrained('flows', 'flow_id')->onDelete('set null'); // Flujo activo
            $table->ulid('current_step_id')->nullable()->constrained('flow_steps', 'step_id')->onDelete('set null');
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->json('context')->nullable(); 
            $table->timestamp('assigned_at')->nullable(); 

            // Declaración Única:
            // $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_agent_id')
                ->nullable()
                ->constrained(
                    config('whatsapp.models.user_table'), // Tabla: users
                    'id' // Columna en users (por defecto 'id')
                )
                ->onDelete('set null');
            
            $table->enum('flow_status', [
                    'started',      // Iniciado
                    'in_progress',  // En progreso
                    'completed',    // Completado
                    'failed',       // Fallido
                    'transferred'   // Transferido a agente
                ])->default('started');
            $table->timestamps();
            $table->softDeletes();

            // Claves foráneas
            $table->foreign('contact_id')->references('contact_id')->on('whatsapp_contacts')->onDelete('cascade');
            $table->foreign('whatsapp_phone_id')->references('phone_number_id')->on('whatsapp_phone_numbers');
            $table->foreign('assigned_bot_id')->references('whatsapp_bot_id')->on('whatsapp_bots')->onDelete('set null');
            $table->foreign('flow_id')->references('flow_id')->on('flows');
            $table->foreign('current_step_id')->references('step_id')->on('flow_steps');

            $table->index('flow_status');
            $table->index('assigned_agent_id');
            $table->index('contact_id');
            $table->index('whatsapp_phone_id');
            $table->index('flow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_sessions');
    }
};
