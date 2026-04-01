<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->string('primary_funding_id', 100)->nullable()->after('message_template_namespace');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->dropColumn('primary_funding_id');
        });
    }
};
