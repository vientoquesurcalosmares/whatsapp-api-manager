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
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // delivered_at ya está indexado en la migración base create_messages_table.
            $table->index('read_at', 'idx_whatsapp_messages_read_at');
            $table->index('failed_at', 'idx_whatsapp_messages_failed_at');
            $table->index('edited_at', 'idx_whatsapp_messages_edited_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('idx_whatsapp_messages_read_at');
            $table->dropIndex('idx_whatsapp_messages_failed_at');
            $table->dropIndex('idx_whatsapp_messages_edited_at');
        });
    }
};
