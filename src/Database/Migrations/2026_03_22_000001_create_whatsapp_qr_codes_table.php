<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whatsapp_qr_codes', function (Blueprint $table) {
            $table->id('qr_id');
            $table->ulid('phone_number_id');
            $table->string('code')->unique();
            $table->text('prefilled_message')->nullable();
            $table->string('deep_link_url');
            $table->text('qr_image_url')->nullable();
            $table->timestamps();

            $table->foreign('phone_number_id')->references('phone_number_id')->on('whatsapp_phone_numbers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whatsapp_qr_codes');
    }
};
