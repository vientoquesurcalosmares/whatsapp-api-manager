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
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('message_id')->primary();
            $table->ulid('whatsapp_phone_id');
            $table->ulid('contact_id');
            $table->ulid('conversation_id')->nullable();
            $table->string('wa_id', 100)->nullable();
            $table->string('messaging_product', 45)->nullable();
            $table->string('message_method', 45)->default('INPUT');
            $table->string('message_from', 45);
            $table->string('message_to', 20);
            $table->string('message_type', 45);
            $table->text('message_content');
            $table->string('media_url', 512)->nullable();
            $table->string('message_context', 45)->nullable();
            $table->string('caption', 45)->nullable();
            $table->json('json_content')->nullable();
            $table->string('status', 45)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('code_error')->nullable();
            $table->text('title_error')->nullable();
            $table->text('message_error')->nullable();
            $table->text('details_error')->nullable();
            $table->json('json')->nullable();
            $table->boolean('bot')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('whatsapp_contacts');

            $table->foreign('conversation_id')
                  ->references('conversation_id')
                  ->on('conversations');

            $table->foreign('whatsapp_phone_id')
                  ->references('phone_number_id')
                  ->on('whatsapp_phone_numbers');

            $table->index('wa_id');
            $table->index('status');
            $table->index('message_type');
            $table->index('delivered_at');
            $table->index(['message_from', 'message_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};