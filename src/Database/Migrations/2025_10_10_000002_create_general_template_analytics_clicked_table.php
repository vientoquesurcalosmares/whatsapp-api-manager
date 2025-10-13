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
        Schema::create('whatsapp_general_template_analytics_clicked', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_template_analytics_id')->constrained('whatsapp_general_template_analytics')->onDelete('cascade');
            $table->string('type', 100)->comment('url_button, unique_url_button, quick_reply, etc.');
            $table->string('button_content', 200)->nullable()->comment('Contenido del botón clickeado');
            $table->integer('count')->default(0)->comment('Número de clicks');
            $table->timestamps();

            // Índices para optimización
            $table->index('general_template_analytics_id', 'idx_w_g_tac_analytics_id');
            $table->index('type', 'idx_w_g_tac_type');
            $table->index(['general_template_analytics_id', 'type'], 'idx_w_g_tac_analytics_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_general_template_analytics_clicked');
    }
};