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
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->string('status')->default('active')->after('whatsapp_business_account_id');
            $table->timestamp('disconnected_at')->nullable()->after('updated_at');
            $table->timestamp('fully_removed_at')->nullable()->after('disconnected_at');
            $table->text('disconnection_reason')->nullable()->after('fully_removed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropColumn(['status', 'disconnected_at', 'fully_removed_at', 'disconnection_reason']);
        });
    }
};