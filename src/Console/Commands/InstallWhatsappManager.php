<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\note;

class InstallWhatsappManager extends Command
{
    /**
     * El nombre y la firma del comando.
     */
    protected $signature = 'whatsapp:install {--force : Sobrescribir archivos existentes}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Instalación guiada y vistosa de WhatsApp API Manager';

    /**
     * Ejecutar el comando.
     */
    public function handle()
    {
        intro('⚡ BIENVENIDO A WHATSAPP API MANAGER');

        // 1. Publicación de Assets
        spin(function () {
            $this->callSilent('vendor:publish', ['--tag' => 'whatsapp-config', '--force' => $this->option('force')]);
            $this->callSilent('vendor:publish', ['--tag' => 'whatsapp-migrations', '--force' => $this->option('force')]);
            $this->callSilent('vendor:publish', ['--tag' => 'whatsapp-routes', '--force' => $this->option('force')]);
        }, 'Publicando configuraciones, migraciones y rutas...');

        // 2. Configuración de Logs (Mezclando el comando existente)
        spin(function () {
            $this->callSilent('whatsapp:merge-logging');
        }, 'Configurando canal de logs "whatsapp"...');

        // 3. Estructura de Almacenamiento
        spin(function () {
            $basePath = storage_path('app/public/whatsapp');
            $folders = ['audios', 'documents', 'images', 'stickers', 'videos', 'flows/keys', 'flows/media'];

            foreach ($folders as $folder) {
                $path = "{$basePath}/{$folder}";
                if (!is_dir($path)) {
                    File::makeDirectory($path, 0755, true);
                }
            }

            // Crear enlace simbólico si no existe
            if (!file_exists(public_path('storage'))) {
                $this->callSilent('storage:link');
            }
        }, 'Preparando directorios en storage y enlaces simbólicos...');

        info('✅ Infraestructura base preparada correctamente.');

        // 4. Fase de Criptografía (WhatsApp Flows)
        // Se añade el hint para evitar confusiones en terminales de Windows
        $wantsKeys = confirm(
            label: '¿Deseas generar automáticamente las llaves RSA para WhatsApp Flows?',
            default: true,
            hint: 'Usa las flechas [↑/↓] para seleccionar, Espacio para marcar y Enter para confirmar.'
        );

        if ($wantsKeys) {
            $this->generateRsaKeys();
        }

        // 5. Verificación de Webhook
        if (empty(config('whatsapp.webhook.verify_token'))) {
            warning('No se detectó un WHATSAPP_VERIFY_TOKEN en tu configuración.');
            note('Recuerda agregarlo a tu archivo .env para que el Webhook funcione correctamente.');
        }

        outro('🚀 INSTALACIÓN COMPLETADA CON ÉXITO');

        $this->line('  📖 Documentación: https://github.com/djdang3r/whatsapp-api-manager');
        $this->line('  💡 Siguiente paso: ejecuta "php artisan migrate"');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Llama al comando independiente de generación de llaves.
     */
    protected function generateRsaKeys()
    {
        // Pasamos el flag --force si el instalador se ejecutó con force
        $params = $this->option('force') ? ['--force' => true] : [];

        $this->call('whatsapp:generate-keys', $params);
    }
}