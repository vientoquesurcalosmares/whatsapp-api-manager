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
        Schema::create('whatsapp_screen_elements', function (Blueprint $table) {
            $table->ulid('screen_element_id')->primary();
            $table->foreignUlid('screen_id')->constrained('whatsapp_flow_screens', 'screen_id')->onDelete('cascade');
            $table->string('type')->comment('input, button, dropdown, etc');
            $table->string('name')->comment('Identificador único en la pantalla');
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->string('default_value')->nullable();
            $table->json('options')->nullable()->comment('Para dropdowns/radios');
            $table->json('style_json')->nullable();
            $table->boolean('required')->default(false);
            $table->json('validation')->nullable();
            $table->string('next_screen')->nullable()->comment('Pantalla destino para botones');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_screen_elements');
    }
};
