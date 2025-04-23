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
        Schema::create('flow_steps', function (Blueprint $table) {
            $table->uuid('step_id')->primary();
            $table->foreignUuid('flow_id')->constrained('flows', 'flow_id');
            $table->integer('order'); // Orden de ejecución
            $table->enum('type', ['text', 'menu', 'input', 'media', 'location', 'document']);
            $table->json('content'); // { "body": "Hola", "buttons": [...] }
            $table->uuid('next_step_id')->nullable(); // Paso siguiente
            $table->boolean('is_terminal')->default(false); // Finaliza el flujo
            $table->timestamps();
            $table->softDeletes();

            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_steps');
    }
};
