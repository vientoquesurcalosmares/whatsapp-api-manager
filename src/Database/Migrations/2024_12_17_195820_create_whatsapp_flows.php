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
        Schema::create('whatsapp_flows', function (Blueprint $table) {
            $table->ulid('flow_id')->primary();
            $table->string('whatsapp_business_account_id', 255);
            $table->string('name')->unique();
            $table->string('wa_flow_id')->nullable()->comment('ID de Flow en Meta');
            $table->string('flow_type')->nullable();
            $table->text('description')->nullable();
            $table->json('json_structure')->nullable()->comment('Estructura JSON del flow');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('version')->default('3.0');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('whatsapp_business_account_id')
                  ->references('whatsapp_business_id')
                  ->on('whatsapp_business_accounts');

            $table->index('whatsapp_business_account_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flows');
    }
};
