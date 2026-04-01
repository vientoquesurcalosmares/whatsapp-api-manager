<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos BSUID a la tabla de mensajes.
 *
 * Con la actualización de WhatsApp del 31 de marzo de 2026:
 * - Los mensajes entrantes incluyen `from_user_id` (BSUID del remitente)
 * - Los webhooks de estado incluyen `recipient_user_id` y `parent_recipient_user_id`
 * - El campo `from` puede estar ausente en mensajes de usuarios con nombres de usuario
 *
 * Cambios:
 * - Se agrega `from_bsuid`              — BSUID del remitente en mensajes entrantes
 * - Se agrega `from_parent_bsuid`       — BSUID principal del remitente
 * - Se agrega `recipient_bsuid`         — BSUID del destinatario en status updates
 * - Se agrega `parent_recipient_bsuid`  — BSUID principal del destinatario en status updates
 * - `message_from` pasa a ser nullable  (puede estar ausente si `from` no llega en el webhook)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // BSUID del remitente (mensajes entrantes)
            $table->string('from_bsuid', 150)->nullable()->after('message_from');
            $table->string('from_parent_bsuid', 150)->nullable()->after('from_bsuid');

            // BSUID del destinatario (status webhooks: delivered, read)
            $table->string('recipient_bsuid', 150)->nullable()->after('message_to');
            $table->string('parent_recipient_bsuid', 150)->nullable()->after('recipient_bsuid');

            // Hacer nullable: `from` puede estar ausente cuando el usuario tiene nombre de usuario
            $table->string('message_from', 45)->nullable()->change();

            $table->index('from_bsuid', 'idx_messages_from_bsuid');
            $table->index('recipient_bsuid', 'idx_messages_recipient_bsuid');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_from_bsuid');
            $table->dropIndex('idx_messages_recipient_bsuid');
            $table->dropColumn([
                'from_bsuid',
                'from_parent_bsuid',
                'recipient_bsuid',
                'parent_recipient_bsuid',
            ]);
            $table->string('message_from', 45)->nullable(false)->change();
        });
    }
};
