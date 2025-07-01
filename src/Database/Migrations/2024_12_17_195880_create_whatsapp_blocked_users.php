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
        Schema::create('whatsapp_blocked_users', function (Blueprint $table) {
            $table->string('blocked_user_id', 26)->primary(); // ULID
            $table->string('phone_number_id', 26);
            $table->string('contact_id', 26)->nullable();
            $table->string('user_wa_id')->comment('WA_ID or phone number');
            $table->timestamp('blocked_at');
            $table->timestamp('unblocked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('phone_number_id')
                  ->references('phone_number_id')
                  ->on('whatsapp_phone_numbers')
                  ->onDelete('cascade');
                  
            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('whatsapp_contacts')
                  ->onDelete('set null');

            $table->index(['phone_number_id', 'user_wa_id']);
            $table->boolean('synced_with_api')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_blocked_users');
    }
};
