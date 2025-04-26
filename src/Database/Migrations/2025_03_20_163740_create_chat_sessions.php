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
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->uuid('session_id')->primary();
            $table->uuid('contact_id');
            $table->uuid('whatsapp_phone_id'); // Número de WhatsApp usado
            $table->uuid('assigned_bot_id')->nullable();
            $table->foreignUuid('flow_id')->nullable(); // Flujo activo
            $table->uuid('current_step_id')->nullable(); 
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->json('context')->nullable(); 
            $table->timestamp('assigned_at')->nullable(); 

            // Declaración Única:
            // $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_agent_id')
                ->nullable()
                ->constrained(config('whatsapp-manager.models.user_table')) // ✅ Usar configuración
                ->onDelete('set null');
            
            $table->enum('flow_status', ['PENDING', 'IN_PROGRESS', 'FINALIZED'])->default('IN_PROGRESS');
            $table->timestamps();
            $table->softDeletes();

            // Claves foráneas
            $table->foreign('contact_id')->references('contact_id')->on('contacts')->onDelete('cascade');
            $table->foreign('whatsapp_phone_id')->references('phone_number_id')->on('whatsapp_phone_numbers');
            $table->foreign('assigned_bot_id')->references('whatsapp_bot_id')->on('whatsapp_bots')->onDelete('set null');
            $table->foreign('flow_id')->references('flow_id')->on('flows');
            $table->foreign('current_step_id')->references('step_id')->on('flow_steps');

            $table->index('flow_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
