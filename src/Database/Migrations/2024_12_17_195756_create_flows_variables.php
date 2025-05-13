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
        Schema::create('flow_variables', function (Blueprint $table) {
            $table->ulid('variable_id')->primary();
            $table->foreignUlid('flow_id')->constrained('flows', 'flow_id')->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('string'); // 'string', 'number', 'boolean'
            $table->json('default_value')->nullable(); // Valor inicial opcional
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_variables');
    }
};
