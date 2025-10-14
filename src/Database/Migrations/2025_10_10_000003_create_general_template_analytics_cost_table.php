<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_general_template_analytics_cost', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('general_template_analytics_id');
            $table->string('type', 100)->comment('amount_spent, cost_per_delivered, cost_per_url_button_click, etc.');
            $table->decimal('value', 10, 4)->nullable()->comment('Valor del costo, puede ser null si no hay datos');
            $table->string('currency', 10)->default('USD')->comment('Moneda del costo');
            $table->timestamps();

            // Índices para optimización
            $table->index('general_template_analytics_id', 'idx_w_g_taco_analytics_id');
            $table->index('type', 'idx_w_g_taco_type');

            // Índice único para evitar duplicados del CRON
            $table->unique(['general_template_analytics_id', 'type'], 'unique_w_g_taco_analytics_type');

            // Clave foránea explícita
            /*$table->foreign('general_template_analytics_id')
                ->references('id')
                ->on('whatsapp_general_template_analytics')
                ->onDelete('cascade')
                ->name('fk_wgtaco_gta_id');*/
        });

        // Agregar la clave foránea con nombre corto, se hizo de esta manera porque el método ->name() para la llave foránea no funciona bien en algunas versiones de Laravel, y no usar el método "->name" hace que el nombre sea muy largo y da error
        DB::statement('ALTER TABLE whatsapp_general_template_analytics_cost ADD CONSTRAINT fk_wgtaco_gta_id FOREIGN KEY (general_template_analytics_id) REFERENCES whatsapp_general_template_analytics(id) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_general_template_analytics_cost');
    }
};