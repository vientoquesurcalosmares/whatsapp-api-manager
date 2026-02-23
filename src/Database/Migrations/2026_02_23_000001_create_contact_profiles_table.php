<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contact_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary(); // ID único para cada registro pivote
            $table->ulid('phone_number_id');
            $table->ulid('contact_id');

            // Campos personalizados que el negocio puede gestionar
            $table->string('profile_picture')->nullable();            // URL o ruta de la imagen personalizada
            $table->string('alias')->nullable();                // Nombre asignado por el negocio
            $table->string('contact_name', 250)->nullable();
            $table->string('first_name', 45)->nullable();
            $table->string('last_name', 45)->nullable();
            $table->string('middle_name', 45)->nullable();
            $table->string('suffix', 45)->nullable();
            $table->string('prefix', 45)->nullable();
            $table->string('organization')->nullable();
            $table->string('department')->nullable();
            $table->string('title')->nullable();
            $table->date('birthday')->nullable();

            $table->timestamp('last_interaction_at')->nullable();     // Última interacción en este número
            $table->json('metadata')->nullable();                     // Otros datos personalizables

            $table->timestamps();
            $table->softDeletes();

            // Clave única para evitar duplicados (un contacto sólo puede tener un perfil por número)
            $table->unique(['phone_number_id', 'contact_id']);

            // Claves foráneas
            $table->foreign('phone_number_id')
                  ->references('phone_number_id')
                  ->on('whatsapp_phone_numbers')
                  ->onDelete('cascade');

            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('whatsapp_contacts')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_phone_number');
    }
};