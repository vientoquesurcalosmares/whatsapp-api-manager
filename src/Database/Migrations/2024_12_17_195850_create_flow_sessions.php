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
        Schema::create('whatsapp_flow_sessions', function (Blueprint $table) {
            $table->ulid('flow_session_id')->primary();
            $table->foreignUlid('flow_id')->constrained('whatsapp_flows', 'flow_id')->onDelete('cascade');
            $table->string('user_phone');
            $table->string('flow_token')->unique()->comment('Token único de sesión');
            $table->string('current_screen')->nullable();
            $table->json('collected_data')->nullable()->comment('Datos recolectados');
            $table->enum('status', ['active', 'completed', 'failed', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('user_phone');
            $table->index('flow_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_sessions');
    }
};
