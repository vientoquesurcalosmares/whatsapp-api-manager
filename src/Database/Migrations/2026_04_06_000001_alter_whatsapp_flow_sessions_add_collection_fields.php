<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos de recolección de datos a whatsapp_flow_sessions:
     * - Hace flow_id nullable (para sesiones orgánicas sin flow en BD)
     * - Campos de ownership: phone_number_id, contact_id, sent_by_user_id
     * - Método de llegada: send_method, is_organic
     * - Datos intermedios del Data API: intermediate_data
     * - Timestamps de lifecycle: completed_at, abandoned_at
     */
    public function up(): void
    {
        Schema::table('whatsapp_flow_sessions', function (Blueprint $table) {
            // Hacer flow_id nullable para soportar sesiones orgánicas sin flow en BD.
            // MySQL requiere drop FK, cambiar columna, recrear FK.
            $table->dropForeign(['flow_id']);
            $table->foreignUlid('flow_id')->nullable()->change();
            $table->foreign('flow_id')
                  ->references('flow_id')
                  ->on('whatsapp_flows')
                  ->onDelete('set null');

            // Campos de ownership
            $table->ulid('phone_number_id')->nullable()->after('flow_id');
            $table->ulid('contact_id')->nullable()->after('phone_number_id');
            $table->unsignedBigInteger('sent_by_user_id')->nullable()->after('contact_id');

            // Método de llegada del flow
            $table->enum('send_method', ['template', 'interactive', 'organic'])
                  ->default('organic')
                  ->after('sent_by_user_id');

            $table->boolean('is_organic')->default(false)->after('send_method');

            // Datos intermedios del Data API (acumulados pantalla por pantalla)
            $table->json('intermediate_data')->nullable()->after('collected_data');

            // Timestamps de lifecycle
            $table->timestamp('completed_at')->nullable()->after('expires_at');
            $table->timestamp('abandoned_at')->nullable()->after('completed_at');

            // Foreign keys
            $table->foreign('phone_number_id')
                  ->references('phone_number_id')
                  ->on('whatsapp_phone_numbers')
                  ->onDelete('set null');

            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('whatsapp_contacts')
                  ->onDelete('set null');

            $table->foreign('sent_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Índices
            $table->index('phone_number_id');
            $table->index('contact_id');
            $table->index('send_method');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_flow_sessions', function (Blueprint $table) {
            $table->dropForeign(['phone_number_id']);
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['sent_by_user_id']);
            $table->dropIndex(['phone_number_id']);
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['send_method']);
            $table->dropIndex(['completed_at']);
            $table->dropColumn([
                'phone_number_id',
                'contact_id',
                'sent_by_user_id',
                'send_method',
                'is_organic',
                'intermediate_data',
                'completed_at',
                'abandoned_at',
            ]);

            // Restaurar flow_id NOT NULL con onDelete cascade (estado original)
            $table->dropForeign(['flow_id']);
            $table->foreignUlid('flow_id')->nullable(false)->change();
            $table->foreign('flow_id')
                  ->references('flow_id')
                  ->on('whatsapp_flows')
                  ->onDelete('cascade');
        });
    }
};
