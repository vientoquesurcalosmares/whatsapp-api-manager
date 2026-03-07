<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;
use ScriptDevelop\WhatsappManager\Models\Template;

class WhatsappBusinessBackfillTemplateVersionDefaultCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:backfill-template-version-default
                            {--chunk=200 : Tamaño de lote para procesar templates}
                            {--dry-run : Simula cambios sin escribir en base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rellena registros faltantes en whatsapp_template_version_default usando la última versión APPROVED por template.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = max((int) $this->option('chunk'), 1);
        $isDryRun = (bool) $this->option('dry-run');

        $processed = 0;
        $created = 0;
        $withoutApprovedVersion = 0;
        $alreadyValid = 0;

        $this->info('Iniciando backfill de whatsapp_template_version_default...');

        Template::query()
            ->select(['template_id'])
            ->orderBy('template_id')
            ->chunk($chunkSize, function ($templates) use (&$processed, &$created, &$withoutApprovedVersion, &$alreadyValid, $isDryRun) {
                foreach ($templates as $template) {
                    $processed++;

                    $defaultVersion = (clone $template->versionDefault())->first();

                    if ($defaultVersion) {
                        $alreadyValid++;
                        continue;
                    }

                    $lastApprovedVersion = $template->versions()
                        ->where('status', 'APPROVED')
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if (!$lastApprovedVersion) {
                        $withoutApprovedVersion++;
                        continue;
                    }

                    if (!$isDryRun) {
                        $templateVersionDefaultModel = config('whatsapp.models.template_version_default');
                        $templateVersionDefaultModel::upsertDefault($template->template_id, $lastApprovedVersion->version_id);
                    }

                    $created++;
                }
            });

        $this->newLine();
        $this->line('Resumen:');
        $this->line(' - Templates procesados: '.$processed);
        $this->line(' - Ya válidos: '.$alreadyValid);
        $this->line(' - Faltantes creados'.($isDryRun ? ' (simulado)' : '').': '.$created);
        $this->line(' - Sin versión APPROVED: '.$withoutApprovedVersion);

        if ($isDryRun) {
            $this->warn('Se ejecutó en modo --dry-run. No se realizaron cambios en base de datos.');
        } else {
            $this->info('Backfill completado.');
        }

        return self::SUCCESS;
    }
}
