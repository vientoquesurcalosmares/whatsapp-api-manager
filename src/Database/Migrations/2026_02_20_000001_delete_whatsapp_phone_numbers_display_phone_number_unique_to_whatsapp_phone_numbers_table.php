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
        if (Schema::hasIndex('whatsapp_phone_numbers', 'whatsapp_phone_numbers_display_phone_number_unique')) {
            Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
                $table->dropUnique('whatsapp_phone_numbers_display_phone_number_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('whatsapp_phone_numbers', 'whatsapp_phone_numbers_display_phone_number_unique')) {
            Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
                $table->unique('display_phone_number', 'whatsapp_phone_numbers_display_phone_number_unique');
            });
        }
    }
};
