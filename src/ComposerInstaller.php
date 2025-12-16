<?php

namespace ScriptDevelop\WhatsappManager;

use Composer\Script\Event;
use Illuminate\Filesystem\Filesystem;

class ComposerInstaller
{
    public static function postInstall(Event $event)
    {
        self::showMessage($event);
    }

    public static function postUpdate(Event $event)
    {
        self::showMessage($event);
    }

    private static function showMessage(Event $event)
    {
        $filesystem = new Filesystem();
        $configPath = config_path('whatsapp.php');

        // Si el archivo de configuración existe, verificar si necesita actualización
        if ($filesystem->exists($configPath)) {
            $configContent = $filesystem->get($configPath);
            
            // Verificar si la configuración del processor está presente
            if (strpos($configContent, "'processor'") === false) {
                // Agregar la configuración del processor manteniendo el formato
                $newConfigContent = str_replace(
                    "'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),",
                    "'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),\n\n    // Procesador personalizado para webhooks\n    'processor' => \ScriptDevelop\WhatsappManager\Services\WebhookProcessors\BaseWebhookProcessor::class,",
                    $configContent
                );
                
                $filesystem->put($configPath, $newConfigContent);
            }
        }

        $io = $event->getIO();
        
        // Verificar si es nuestro paquete
        $package = $event->getComposer()->getPackage();
        if ($package->getName() !== 'scriptdevelop/whatsapp-manager') {
            return;
        }
        
        // Mensaje de éxito
        $io->write('  <bg=green;fg=white> SUCCESS </> <fg=green>' . whatsapp_trans('messages.installation_complete') . '</>');
        $io->write('');

        // Mensaje de agradecimiento
        $io->write('  <fg=yellow>' . whatsapp_trans('messages.thank_you') . '</>');
        $io->write('');

        // Instrucciones
        $io->write('  <options=bold>' . whatsapp_trans('messages.next_steps') . ':</>');
        $io->write('  <fg=yellow>1. ' . whatsapp_trans('messages.publish_config') . ':</>');
        $io->write('     <fg=cyan>php artisan vendor:publish --provider="ScriptDevelop\\WhatsappManager\\Providers\\WhatsappServiceProvider"</>');
        $io->write('');

        // Mensaje de apoyo
        $io->write('  <fg=yellow>2. ' . whatsapp_trans('messages.support_message') . '</>');
        $io->write('     <fg=blue;options=underscore>https://github.com/djdang3r/whatsapp-api-manager</>');
        $io->write('');
    }
}