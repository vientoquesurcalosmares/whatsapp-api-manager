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
        Schema::create('flows', function (Blueprint $table) {
            $table->ulid('flow_id')->primary();
            // $table->foreignUlid('whatsapp_bot_id')->constrained('whatsapp_bots', 'whatsapp_bot_id')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('trigger_keywords')->nullable(); // Palabra clave o palabras claves para activar el flujo o respuestas.
            $table->boolean('is_case_sensitive')->default(false);
            $table->boolean('is_default')->default(false); // Flujo por defecto
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_bot_id']); // Eliminar clave foránea
        });
        
        Schema::dropIfExists('flows');
    }
};
