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
        Schema::create('whatsapp_template_media_files', function (Blueprint $table) {
            $table->ulid('template_media_file_id')->primary();
            $table->ulid('version_id');
            $table->string('media_type', 45)->index();
            $table->string('file_name', 50);
            $table->string('mime_type', 100);
            $table->string('sha256', 64)->nullable();
            $table->text('url');
            $table->string('media_id', 45)->index()->nullable();
            $table->string('file_size', 45)->nullable();
            $table->string('animated', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['version_id', 'media_type'], 'wtmf_version_media_type_index');

            $table->foreign('version_id')
                ->references('version_id')
                ->on('whatsapp_template_versions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_media_files');
    }
};
