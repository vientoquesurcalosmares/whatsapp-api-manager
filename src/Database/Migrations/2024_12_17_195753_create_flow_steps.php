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
            $table->ulid('step_id')->primary();
            $table->foreignUlid('flow_id')->constrained('flows', 'flow_id')->onDelete('cascade');
            $table->integer('order'); // Orden de ejecución
            $table->enum('type', ['text','media', 'location','document','message','menu','input','condition','api_call']);
            $table->json('content'); // { "body": "Hola", "buttons": [...] }
            $table->foreignUlid('next_step_id')->nullable()->constrained('flow_steps', 'step_id')->onDelete('set null');
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
