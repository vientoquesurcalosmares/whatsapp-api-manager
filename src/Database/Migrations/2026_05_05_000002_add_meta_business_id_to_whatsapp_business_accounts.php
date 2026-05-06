<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_business_accounts', 'meta_business_id')) {
                $table->string('meta_business_id')->nullable()->after('whatsapp_business_id');
                $table->index('meta_business_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->dropIndex(['meta_business_id']);
            $table->dropColumn('meta_business_id');
        });
    }
};
