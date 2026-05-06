<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;

class PublishWhatsappLang extends Command
{
    /**
     * El nombre y la firma del comando.
     */
    protected $signature = 'whatsapp:publish-lang {--force : Sobrescribir archivos existentes}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Publica los archivos de idioma del paquete WhatsApp Manager';

    /**
     * Ejecutar el comando.
     */
    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'whatsapp-lang',
            '--force' => $this->option('force'),
        ]);

        $this->info('Archivos de idioma publicados con la etiqueta whatsapp-lang.');
        $this->line('Destino: lang/vendor/whatsapp');

        return self::SUCCESS;
    }
}