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
        Schema::create('user_responses', function (Blueprint $table) {
            $table->uuid('response_id')->primary();
            $table->foreignUuid('session_id')->constrained('chat_sessions', 'session_id');
            $table->foreignUuid('flow_step_id')->constrained('flow_steps', 'step_id');
            $table->string('field_name'); // Ej: "nombre", "telefono"
            $table->text('field_value'); // Valor proporcionado
            $table->foreignUuid('contact_id')->constrained('contacts', 'contact_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_responses');
    }
};
