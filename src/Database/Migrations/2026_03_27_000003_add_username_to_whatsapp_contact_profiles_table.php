<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo `username` a los perfiles de contacto.
 *
 * Los perfiles de contacto ahora pueden incluir el nombre de usuario de WhatsApp
 * del contacto, el cual se incluye en el objeto `contacts[].profile.username`
 * de los webhooks cuando el usuario tiene activada la función de nombres de usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contact_profiles', function (Blueprint $table) {
            $table->string('username', 40)->nullable()->after('alias');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contact_profiles', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
