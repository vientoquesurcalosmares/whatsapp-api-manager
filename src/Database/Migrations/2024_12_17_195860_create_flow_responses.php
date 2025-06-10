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
        Schema::create('whatsapp_flow_responses', function (Blueprint $table) {
            $table->ulid('flow_response_id')->primary();
            $table->foreignUlid('session_id')->constrained('whatsapp_flow_sessions', 'flow_session_id')->onDelete('cascade');
            $table->foreignUlid('screen_id')->constrained('whatsapp_flow_screens', 'screen_id')->onDelete('cascade');
            $table->string('element_name');
            $table->text('response_value');
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('element_name');
            $table->index('responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_responses');
    }
};
