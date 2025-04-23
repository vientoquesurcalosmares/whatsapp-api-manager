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
        Schema::create('whatsapp_bots', function (Blueprint $table) {
            $table->uuid('whatsapp_bot_id')->primary();
            $table->string('bot_name', 45);
            $table->integer('port');
            $table->string('url', 45);
            $table->boolean('is_enabled')->default(true); // Habilitar/deshabilitar bot
            $table->foreignUuid('default_flow_id')->nullable(); // Flujo por defecto
            $table->enum('on_failure', ['assign_agent', 'notify'])->default('assign_agent'); // Acción si falla el flujo
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bots');
    }
};
