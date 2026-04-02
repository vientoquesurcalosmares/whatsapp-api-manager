<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_qr_codes', function (Blueprint $table) {
            $table->string('qr_image_path')->nullable()->after('qr_image_url');
            $table->string('qr_image_format', 10)->nullable()->after('qr_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_qr_codes', function (Blueprint $table) {
            $table->dropColumn(['qr_image_path', 'qr_image_format']);
        });
    }
};
