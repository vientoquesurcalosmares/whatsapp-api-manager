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
        Schema::create('whatsapp_flow_screens', function (Blueprint $table) {
            $table->ulid('screen_id')->primary();
            $table->foreignUlid('flow_id')->constrained('whatsapp_flows', 'flow_id')->onDelete('cascade');
            $table->string('name');
            $table->string('title');
            $table->text('content')->nullable();
            $table->boolean('is_start')->default(false);
            $table->integer('order')->default(0);
            $table->json('validation_rules')->nullable();
            $table->json('next_screen_logic')->nullable()->comment('Lógica para siguiente pantalla');
            $table->json('extra_logic')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_screens');
    }
};
