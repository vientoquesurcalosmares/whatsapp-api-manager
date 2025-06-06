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
        Schema::create('whatsapp_flow_events', function (Blueprint $table) {
            $table->ulid('flow_event_id')->primary();
            $table->foreignUlid('session_id')->constrained('whatsapp_flow_sessions', 'flow_session_id')->onDelete('cascade');
            $table->string('event_type'); // started, screen_shown, user_responded, etc
            $table->json('metadata')->nullable();

            $table->timestamp('created_at');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_events');
    }
};
