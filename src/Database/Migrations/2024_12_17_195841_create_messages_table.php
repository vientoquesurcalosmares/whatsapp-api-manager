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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->ulid('message_id')->primary();
            $table->ulid('whatsapp_phone_id');
            $table->ulid('contact_id');
            $table->ulid('conversation_id')->nullable();
            $table->string('wa_id', 100)->nullable();
            $table->string('messaging_product', 45)->nullable();
            $table->string('message_method', 45)->default('INPUT');
            $table->string('message_from', 45);
            $table->string('message_to', 45);
            $table->string('message_type', 45);// Tipo de mensaje soportados: 'text', 'image', 'video', 'audio', 'document', 'sticker', 'location', 'contact', 'button', 'template'
            $table->text('message_content')->nullable();
            $table->string('media_url', 512)->nullable();
            $table->text('message_context')->nullable();
            $table->ulid('message_context_id')->nullable();
            $table->string('message_context_from', 45)->nullable();
            $table->string('caption', 45)->nullable();
            $table->ulid('template_version_id')->nullable();
            $table->json('json_content')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed', 'received'])->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('code_error')->nullable();
            $table->text('title_error')->nullable();
            $table->text('message_error')->nullable();
            $table->text('details_error')->nullable();
            $table->json('json')->nullable();
            $table->json('json_template_payload')->nullable();
            $table->boolean('bot')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('whatsapp_contacts')
                  ->onDelete('cascade');

            $table->foreign('conversation_id')
                  ->references('conversation_id')
                  ->on('whatsapp_conversations')
                  ->onDelete('cascade');

            $table->foreign('whatsapp_phone_id')
                  ->references('phone_number_id')
                  ->on('whatsapp_phone_numbers')
                  ->onDelete('cascade');

            $table->foreign('template_version_id')
                  ->references('version_id')
                  ->on('whatsapp_template_versions')
                  ->onDelete('set null');

            $table->foreign('message_context_id')
                  ->references('message_id')
                  ->on('whatsapp_messages')
                  ->onDelete('set null'); // Si el mensaje de contexto se elimina, se establece como null

            $table->index(['message_from', 'message_to', 'created_at']);
            $table->index('wa_id');
            $table->index('status');
            $table->index('message_type');
            $table->index('delivered_at');
            $table->index(['message_from', 'message_to']);

            $table->index(['contact_id', 'whatsapp_phone_id'], 'idx_contact_whatsapp_phone');
            $table->index(['contact_id', 'message_method'], 'idx_contact_message_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};



// <?php

// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     /**
//      * Run the migrations.
//      */
//     public function up(): void
//     {
//         Schema::create('whatsapp_messages', function (Blueprint $table) {
//             $table->ulid('message_id')->primary(); // Esto ya asegura que sea único
//             $table->ulid('whatsapp_phone_id');
//             $table->ulid('contact_id');
//             $table->ulid('conversation_id')->nullable();
//             $table->string('wa_id', 100)->nullable();
//             $table->string('messaging_product', 45)->nullable();
//             $table->string('message_method', 45)->default('INPUT');
//             $table->string('message_from', 45);
//             $table->string('message_to', 45);
//             $table->string('message_type', 45);
//             $table->text('message_content')->nullable();
//             $table->string('media_url', 512)->nullable();
//             $table->text('message_context')->nullable();
//             $table->ulid('message_context_id')->nullable();
//             $table->string('message_context_from', 45)->nullable();
//             $table->string('caption', 512)->nullable(); // Aumenté el tamaño para compatibilidad
//             $table->ulid('template_version_id')->nullable();
//             $table->json('json_content')->nullable();
//             $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed', 'received'])->default('pending');
//             $table->timestamp('delivered_at')->nullable();
//             $table->timestamp('read_at')->nullable();
//             $table->timestamp('edited_at')->nullable();
//             $table->timestamp('failed_at')->nullable();
//             $table->integer('code_error')->nullable();
//             $table->text('title_error')->nullable();
//             $table->text('message_error')->nullable();
//             $table->text('details_error')->nullable();
//             $table->json('json')->nullable();
//             $table->json('json_template_payload')->nullable();
//             $table->boolean('bot')->default(false);
//             $table->timestamps();
//             $table->softDeletes();

//             // Claves foráneas
//             $table->foreign('contact_id')
//                   ->references('contact_id')
//                   ->on('whatsapp_contacts')
//                   ->onDelete('cascade');

//             $table->foreign('conversation_id')
//                   ->references('conversation_id')
//                   ->on('whatsapp_conversations')
//                   ->onDelete('cascade');

//             $table->foreign('whatsapp_phone_id')
//                   ->references('phone_number_id')
//                   ->on('whatsapp_phone_numbers')
//                   ->onDelete('cascade');

//             $table->foreign('template_version_id')
//                   ->references('version_id')
//                   ->on('whatsapp_template_versions')
//                   ->onDelete('set null');

//             // Clave foránea auto-referenciada - AGREGAR DESPUÉS DE CREAR LA TABLA
//         });

//         // Agregar la clave foránea auto-referenciada en una operación separada
//         Schema::table('whatsapp_messages', function (Blueprint $table) {
//             $table->foreign('message_context_id')
//                   ->references('message_id')
//                   ->on('whatsapp_messages')
//                   ->onDelete('set null');
//         });
//     }

//     /**
//      * Reverse the migrations.
//      */
//     public function down(): void
//     {
//         // Eliminar la clave foránea primero
//         Schema::table('whatsapp_messages', function (Blueprint $table) {
//             $table->dropForeign(['message_context_id']);
//         });

//         Schema::dropIfExists('whatsapp_messages');
//     }
// };
