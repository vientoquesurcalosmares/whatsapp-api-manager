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
            // Índice compuesto para optimizar la consulta de actualización masiva en handleMessageAction
            $table->index([
                'contact_id',
                'whatsapp_phone_id',
                'message_method',
                'status',
                'message_id',
            ], 'idx_mass_read_by_contact_phone_method_status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('idx_mass_read_by_contact_phone_method_status_id');
        });
    }
};
