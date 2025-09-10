<?php

namespace ScriptDevelop\WhatsappManager;

use Composer\Script\Event;

class ComposerInstaller
{
    public static function postInstall(Event $event)
    {
        self::showMessage($event, 'install');
    }

    public static function postUpdate(Event $event)
    {
        self::showMessage($event, 'update');
    }

    private static function showMessage(Event $event, $type)
    {
        // Verifica si el paquete actual es el que se está instalando/actualizando
        $composer = $event->getComposer();
        $package = $composer->getPackage();
        if ($package->getName() === 'tu-vendor/whatsapp-manager') {
            $output = $event->getIO();

            $output->write("\n");
            $output->write("  <bg=green;fg=white> SUCCESS </> <fg=green>WhatsApp API Manager instalado correctamente.</>");
            $output->write("\n\n");
            $output->write("  <fg=yellow>🎉 ¡Gracias por elegir nuestro paquete! 🎉</>");
            $output->write("\n\n");
            $output->write("  <options=bold>Siguientes Pasos:</>");
            $output->write("\n");
            $output->write("  <fg=yellow>1. Publica los archivos de configuración y migraciones ejecutando:</>");
            $output->write("\n");
            $output->write("     <fg=cyan>php artisan vendor:publish --provider=\"ScriptDevelop\\WhatsappManager\\Providers\\WhatsappServiceProvider\"</>");
            $output->write("\n\n");
            $output->write("  <fg=yellow>2. Si este paquete te es útil, considera darle una estrella en GitHub.</>");
            $output->write("\n");
            $output->write("     <fg=yellow>Tu apoyo nos ayuda a crecer y mejorar.</>");
            $output->write("\n");
            $output->write("     <fg=blue;options=underscore>https://github.com/djdang3r/whatsapp-api-manager</>");
            $output->write("\n\n");
        }
    }
}