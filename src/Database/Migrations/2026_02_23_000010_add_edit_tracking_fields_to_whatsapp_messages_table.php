<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Campo para que el mensaje editado apunte al original
            $table->ulid('original_message_id')->nullable()->after('edited_at');
            $table->foreign('original_message_id')
                  ->references('message_id')
                  ->on('whatsapp_messages')
                  ->onDelete('set null');

            // Indicador de que el mensaje original fue editado
            $table->boolean('is_edited')->default(false)->after('original_message_id');

            // Referencia al último mensaje de edición (en el original)
            $table->ulid('last_edit_message_id')->nullable()->after('is_edited');
            $table->foreign('last_edit_message_id')
                  ->references('message_id')
                  ->on('whatsapp_messages')
                  ->onDelete('set null');

            // Colocamos los campos después de last_edit_message_id (que está después de is_edited)
            $table->boolean('is_revoked')->default(false)->after('last_edit_message_id');
            $table->timestamp('revoked_at')->nullable()->after('is_revoked');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['original_message_id']);
            $table->dropForeign(['last_edit_message_id']);
            $table->dropColumn(['original_message_id', 'is_edited', 'last_edit_message_id']);
            $table->dropColumn(['is_revoked', 'revoked_at']);
        });
    }
};