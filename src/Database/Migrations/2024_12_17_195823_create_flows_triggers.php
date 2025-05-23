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
        Schema::create('flow_triggers', function (Blueprint $table) {
            $table->ulid('trigger_id')->primary();
            $table->enum('trigger_type', ['keyword','template','default'])->default('keyword');
            $table->unsignedInteger('priority')->default(0);
            $table->foreignUlid('flow_id')->constrained('flows', 'flow_id')->onDelete('cascade');
            $table->enum('type', ['keyword', 'event', 'schedule']);
            $table->string('value'); // Ej: "hola", "ON_SUBSCRIBE", "08:00"
        
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_triggers');
    }
};
