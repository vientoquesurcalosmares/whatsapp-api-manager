<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte para identificadores de usuario específicos de empresa (BSUID).
 *
 * A partir del 31 de marzo de 2026, WhatsApp incluirá el BSUID (Business-Scoped User ID)
 * en todos los webhooks de mensajes. Este identificador es único por usuario y portfolio
 * comercial, y puede estar presente incluso cuando el número de teléfono no lo esté
 * (por ejemplo, cuando el usuario activa la función de nombres de usuario).
 *
 * Cambios en esta migración:
 * - Se agrega `bsuid`         — identificador BSUID del usuario (nuevo identificador primario)
 * - Se agrega `parent_bsuid`  — BSUID principal (para portfolios comerciales vinculados)
 * - Se agrega `username`      — nombre de usuario de WhatsApp (opcional, elegido por el usuario)
 * - `country_code` pasa a ser nullable (puede estar ausente si solo llega el BSUID)
 * - `phone_number` pasa a ser nullable (ídem)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            // Nuevo identificador principal: siempre presente en webhooks desde el 31/03/2026
            $table->string('bsuid', 150)->nullable()->unique()->after('wa_id');

            // BSUID principal: presente solo si el negocio tiene portfolios vinculados
            $table->string('parent_bsuid', 150)->nullable()->after('bsuid');

            // Nombre de usuario de WhatsApp: presente solo si el usuario lo activó
            $table->string('username', 40)->nullable()->after('parent_bsuid');

            // Hacer nullable: pueden estar ausentes cuando el usuario activa nombres de usuario
            $table->string('country_code', 45)->nullable()->change();
            $table->string('phone_number', 45)->nullable()->change();

            $table->index('bsuid', 'idx_contacts_bsuid');
            $table->index('parent_bsuid', 'idx_contacts_parent_bsuid');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_bsuid');
            $table->dropIndex('idx_contacts_parent_bsuid');
            $table->dropUnique(['bsuid']);
            $table->dropColumn(['bsuid', 'parent_bsuid', 'username']);

            $table->string('country_code', 45)->nullable(false)->change();
            $table->string('phone_number', 45)->nullable(false)->change();
        });
    }
};
