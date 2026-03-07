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
        Schema::create('whatsapp_template_version_default', function (Blueprint $table) {
            $table->ulid('default_id')->primary();
            $table->ulid('template_id')->unique();
            $table->ulid('version_id')->index();
            $table->timestamps();

            $table->index(['template_id', 'version_id'], 'idx_template_version_default');

            $table->foreign('template_id')
                ->references('template_id')
                ->on('whatsapp_templates')
                ->onDelete('cascade');

            $table->foreign('version_id')
                ->references('version_id')
                ->on('whatsapp_template_versions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_version_default');
    }
};
