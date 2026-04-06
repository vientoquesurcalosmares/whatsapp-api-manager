<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos de enriquecimiento a whatsapp_flow_responses:
     * - Hace screen_id nullable (flujos orgánicos no tienen screens estructuradas en BD)
     * - Campos de ownership: phone_number_id, contact_id
     * - Enriquecimiento: field_type, raw_value, display_value, screen_name
     */
    public function up(): void
    {
        Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
            // Hacer screen_id nullable (flujos orgánicos no tienen screens en BD).
            // MySQL requiere drop FK, cambiar columna, recrear FK con SET NULL.
            $table->dropForeign(['screen_id']);
            $table->foreignUlid('screen_id')->nullable()->change();
            $table->foreign('screen_id')
                  ->references('screen_id')
                  ->on('whatsapp_flow_screens')
                  ->onDelete('set null');

            // Campos de ownership (denormalizados para queries rápidas sin joins)
            $table->ulid('phone_number_id')->nullable()->after('session_id');
            $table->ulid('contact_id')->nullable()->after('phone_number_id');

            // Enriquecimiento de campo
            $table->string('screen_name', 100)->nullable()->after('screen_id');
            $table->string('field_type', 50)->nullable()->after('element_name');
            $table->text('raw_value')->nullable()->after('response_value');
            $table->text('display_value')->nullable()->after('raw_value');

            // FKs
            $table->foreign('phone_number_id')
                  ->references('phone_number_id')
                  ->on('whatsapp_phone_numbers')
                  ->onDelete('set null');

            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('whatsapp_contacts')
                  ->onDelete('set null');

            // Índices
            $table->index('phone_number_id');
            $table->index('contact_id');
            $table->index('screen_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
            $table->dropForeign(['phone_number_id']);
            $table->dropForeign(['contact_id']);
            $table->dropIndex(['phone_number_id']);
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['screen_name']);
            $table->dropColumn([
                'phone_number_id',
                'contact_id',
                'screen_name',
                'field_type',
                'raw_value',
                'display_value',
            ]);

            // Restaurar screen_id NOT NULL con onDelete cascade (estado original)
            $table->dropForeign(['screen_id']);
            $table->foreignUlid('screen_id')->nullable(false)->change();
            $table->foreign('screen_id')
                  ->references('screen_id')
                  ->on('whatsapp_flow_screens')
                  ->onDelete('cascade');
        });
    }
};
