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
        Schema::create('whatsapp_template_versions', function (Blueprint $table) {
            $table->ulid('version_id')->primary();
            $table->ulid('template_id');
            $table->string('version_hash', 64)->comment('Hash SHA256 del contenido para control de cambios');
            $table->json('template_structure')->comment('JSON completo de la plantilla en esta versión');
            $table->enum('status', ['APPROVED', 'PENDING', 'REJECTED'])->default('PENDING');
            $table->string('rejection_reason', 512)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('template_id')
                  ->references('template_id')
                  ->on('whatsapp_templates')
                  ->onDelete('cascade');

            $table->unique(['template_id', 'version_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_versions');
    }
};
