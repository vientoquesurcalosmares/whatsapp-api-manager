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
            $table->ulid('whatsapp_bot_id')->primary();
            $table->foreignUlid('phone_number_id')->constrained('whatsapp_phone_numbers', 'phone_number_id')->onDelete('cascade');
            $table->string('bot_name', 45);
            $table->text('description')->nullable();
            $table->boolean('is_enable')->default(false); // Habilitar/deshabilitar bot
            $table->enum('on_failure', ['assign_agent', 'notify'])->default('assign_agent'); // Acción si falla el flujo
            $table->text('failure_message')->nullable();
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
