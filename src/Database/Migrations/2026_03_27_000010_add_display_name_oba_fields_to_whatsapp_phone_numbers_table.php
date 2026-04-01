<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            // Nombre visible en revisión (pendiente de aprobación por Meta)
            $table->string('new_display_name', 150)->nullable()->after('verified_name');
            $table->string('new_name_status', 50)->nullable()->after('new_display_name');

            // Estado de solicitud de Cuenta de Empresa Oficial (OBA)
            // Posibles valores: NOT_STARTED, PENDING, APPROVED, REJECTED
            $table->string('oba_status', 50)->nullable()->after('is_official');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropColumn(['new_display_name', 'new_name_status', 'oba_status']);
        });
    }
};
