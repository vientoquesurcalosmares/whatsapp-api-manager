<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_template_version_default')) {
            return;
        }

        if (!Schema::hasTable('whatsapp_templates') || !Schema::hasTable('whatsapp_template_versions')) {
            return;
        }

        Artisan::call('whatsapp-business:backfill-template-version-default', [
            '--chunk' => 200,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill one-shot migration. No reversible action required.
    }
};
