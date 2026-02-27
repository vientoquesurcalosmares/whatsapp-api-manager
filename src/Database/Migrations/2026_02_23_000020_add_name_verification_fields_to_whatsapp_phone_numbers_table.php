<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            // Campos para almacenar la última solicitud de verificación de nombre
            $table->string('requested_verified_name', 150)->nullable()->after('verified_name');
            $table->string('name_decision', 50)->nullable()->after('requested_verified_name');
            $table->string('name_rejection_reason', 100)->nullable()->after('name_decision');
            $table->timestamp('name_verified_at')->nullable()->after('name_rejection_reason');
            // Agregar campo para el nivel de límite de mensajes (TIER_50, TIER_2K, etc.)
            $table->string('messaging_limit_tier', 50)->nullable()->after('quality_rating');
            // Timestamp de la última actualización del límite
            $table->timestamp('messaging_limit_updated_at')->nullable()->after('messaging_limit_tier');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropColumn([
                'requested_verified_name',
                'name_decision',
                'name_rejection_reason',
                'name_verified_at',
                'messaging_limit_tier',
                'messaging_limit_updated_at'
            ]);
        });
    }
};