<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_flow', function (Blueprint $table) {
            $table->ulid('bot_flow_id')->primary();
            $table->foreignUlid('whatsapp_bot_id')
                  ->constrained('whatsapp_bots', 'whatsapp_bot_id')
                  ->cascadeOnDelete();
            $table->foreignUlid('flow_id')
                  ->constrained('flows', 'flow_id')
                  ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['whatsapp_bot_id', 'flow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_flow');
    }
};
