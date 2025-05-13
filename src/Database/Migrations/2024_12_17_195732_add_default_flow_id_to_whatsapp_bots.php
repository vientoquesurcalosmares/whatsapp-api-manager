<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_bots', function (Blueprint $table) {
            $table->foreignUlid('default_flow_id')->nullable()->after('is_enabled');
            $table->foreign('default_flow_id')->references('flow_id')->on('flows')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_bots', function (Blueprint $table) {
            $table->dropForeign(['default_flow_id']);
            $table->dropColumn('default_flow_id');
        });
    }
};