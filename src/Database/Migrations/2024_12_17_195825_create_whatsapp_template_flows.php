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
        Schema::create('whatsapp_template_flows', function (Blueprint $table) {
            $table->ulid('template_flow_id')->primary();

            // Clave foránea a la plantilla
            $table->foreignUlid('template_id')
                ->constrained('whatsapp_templates', 'template_id')
                ->onDelete('cascade');

            // Clave foránea al flujo
            $table->foreignUlid('flow_id')
                ->constrained('whatsapp_flows', 'flow_id')
                ->onDelete('cascade');

            $table->string('flow_button_label', 100)->nullable()->comment('Texto del botón que lanza el Flow');
            $table->json('flow_variables')->nullable()->comment('Valores prellenados que se pasan al Flow');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['template_id', 'flow_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_flows');
    }
};
