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
            $table->string('messaging_limit_tier', 50)->nullable()->after('message_template_namespace');
            $table->integer('messaging_limit_value')->nullable()->after('messaging_limit_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->dropColumn(['messaging_limit_tier', 'messaging_limit_value']);
        });
    }
};

