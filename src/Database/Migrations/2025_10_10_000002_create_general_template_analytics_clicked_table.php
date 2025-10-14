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
        Schema::create('whatsapp_general_template_analytics_clicked', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('general_template_analytics_id');
            $table->string('type', 100)->comment('url_button, unique_url_button, quick_reply, etc.');
            $table->string('button_content', 200)->nullable()->comment('Contenido del botón clickeado');
            $table->integer('count')->default(0)->comment('Número de clicks');
            $table->timestamps();

            // Índices para optimización
            $table->index('general_template_analytics_id', 'idx_w_g_tac_analytics_id');
            $table->index('type', 'idx_w_g_tac_type');

            // Índice único para evitar duplicados del CRON
            $table->unique(['general_template_analytics_id', 'type', 'button_content'], 'unique_w_g_tac_analytics_type');

            // Clave foránea explícita
            /*$table->foreign('general_template_analytics_id')
                ->references('id')
                ->on('whatsapp_general_template_analytics')
                ->onDelete('cascade')
                ->name('fk_wgtac_gta_id');*/
        });

        // Agregar la clave foránea con nombre corto, se hizo de esta manera porque el método ->name() para la llave foránea no funciona bien en algunas versiones de Laravel, y no usar el método "->name" hace que el nombre sea muy largo y da error
        DB::statement('ALTER TABLE whatsapp_general_template_analytics_clicked ADD CONSTRAINT fk_wgtac_gta_id FOREIGN KEY (general_template_analytics_id) REFERENCES whatsapp_general_template_analytics(id) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_general_template_analytics_clicked');
    }
};