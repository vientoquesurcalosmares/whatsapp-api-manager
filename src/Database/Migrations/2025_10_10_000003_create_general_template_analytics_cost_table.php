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
        Schema::create('whatsapp_general_template_analytics_cost', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_template_analytics_id')->constrained('whatsapp_general_template_analytics')->onDelete('cascade');
            $table->string('type', 100)->comment('amount_spent, cost_per_delivered, cost_per_url_button_click, etc.');
            $table->decimal('value', 10, 4)->nullable()->comment('Valor del costo, puede ser null si no hay datos');
            $table->string('currency', 10)->default('USD')->comment('Moneda del costo');
            $table->timestamps();

            // Índices para optimización
            $table->index('general_template_analytics_id', 'idx_w_g_taco_analytics_id');
            $table->index('type', 'idx_w_g_taco_type');
            $table->index(['general_template_analytics_id', 'type'], 'idx_w_g_taco_analytics_type');
            $table->index('value', 'idx_w_g_taco_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_general_template_analytics_cost');
    }
};