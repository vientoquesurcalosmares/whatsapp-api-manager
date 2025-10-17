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
        // Agregar campos a la tabla whatsapp_messages
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Campos para coexistencia
            $table->boolean('is_historical')->default(false)->after('bot');
            $table->integer('historical_phase')->nullable()->after('is_historical');
            $table->boolean('is_smb_echo')->default(false)->after('historical_phase');
            
            // Índices para los nuevos campos
            $table->index('is_historical');
            $table->index('historical_phase');
            $table->index('is_smb_echo');
            $table->index(['is_historical', 'historical_phase']);
        });

        // Agregar campo a la tabla whatsapp_contacts si no existe
        if (!Schema::hasColumn('whatsapp_contacts', 'accepts_marketing')) {
            Schema::table('whatsapp_contacts', function (Blueprint $table) {
                $table->boolean('accepts_marketing')->default(true)->after('last_name');
                $table->timestamp('marketing_opt_out_at')->nullable()->after('accepts_marketing');
            });
        }

        // Asegurar que la tabla whatsapp_contacts tenga soft deletes
        if (!Schema::hasColumn('whatsapp_contacts', 'deleted_at')) {
            Schema::table('whatsapp_contacts', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Agregar índices para mejor performance en búsquedas de coexistencia
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index(['contact_id', 'is_historical']);
            $table->index(['whatsapp_phone_id', 'created_at']);
            $table->index(['status', 'is_historical']);
        });

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->index('accepts_marketing');
            $table->index('marketing_opt_out_at');
            $table->index(['country_code', 'phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir cambios en whatsapp_messages
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Eliminar índices primero
            $table->dropIndex(['is_historical']);
            $table->dropIndex(['historical_phase']);
            $table->dropIndex(['is_smb_echo']);
            $table->dropIndex(['is_historical', 'historical_phase']);
            $table->dropIndex(['contact_id', 'is_historical']);
            $table->dropIndex(['whatsapp_phone_id', 'created_at']);
            $table->dropIndex(['status', 'is_historical']);
            
            // Eliminar columnas
            $table->dropColumn(['is_historical', 'historical_phase', 'is_smb_echo']);
        });

        // Revertir cambios en whatsapp_contacts (opcional - si quieres eliminar completamente)
        // Schema::table('whatsapp_contacts', function (Blueprint $table) {
        //     $table->dropColumn(['accepts_marketing', 'marketing_opt_out_at']);
        //     $table->dropSoftDeletes();
        // });
    }
};