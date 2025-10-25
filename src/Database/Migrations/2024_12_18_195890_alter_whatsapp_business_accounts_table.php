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
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            // Agregar nuevos campos
            $table->string('status')->default('active')->after('whatsapp_business_id');
            $table->timestamp('disconnected_at')->nullable()->after('updated_at');
            $table->timestamp('fully_removed_at')->nullable()->after('disconnected_at');
            $table->text('disconnection_reason')->nullable()->after('fully_removed_at');
            
            // Cambiar partner_app_id a nullable si no lo está
            if (Schema::hasColumn('whatsapp_business_accounts', 'partner_app_id')) {
                $table->string('partner_app_id', 100)->nullable()->change();
            } else {
                $table->string('partner_app_id', 100)->nullable()->after('app_link');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->dropColumn(['status', 'disconnected_at', 'fully_removed_at', 'disconnection_reason']);
            // No revertir el cambio a nullable para mantener compatibilidad
        });
    }
};