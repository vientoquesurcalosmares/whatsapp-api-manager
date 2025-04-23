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
        Schema::table('whatsapp_bots', function (Blueprint $table) {
            $table->foreign('default_flow_id')->references('flow_id')->on('flows')->onDelete('set null');
        });

        Schema::table('flows', function (Blueprint $table) {
            $table->foreign('bot_id')->references('whatsapp_bot_id')->on('whatsapp_bots')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_bots', function (Blueprint $table) {
            $table->dropForeign(['default_flow_id']);
        });

        Schema::table('flows', function (Blueprint $table) {
            $table->dropForeign(['bot_id']);
        });
    }
};
