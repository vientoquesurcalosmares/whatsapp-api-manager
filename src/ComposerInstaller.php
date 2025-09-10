<?php

namespace ScriptDevelop\WhatsappManager;

use Composer\Script\Event;

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
        $io = $event->getIO();
        
        // Espacio en blanco superior
        $io->write('');
        
        // Mensaje de éxito
        $io->write('  <bg=green;fg=white> SUCCESS </> <fg=green>WhatsApp API Manager instalado correctamente.</>');
        $io->write('');
        
        // Mensaje de agradecimiento
        $io->write('  <fg=yellow>🎉 ¡Gracias por elegir nuestro paquete! 🎉</>');
        $io->write('');
        
        // Instrucciones
        $io->write('  <options=bold>Siguientes Pasos:</>');
        $io->write('  <fg=yellow>1. Publica los archivos de configuración y migraciones ejecutando:</>');
        $io->write('     <fg=cyan>php artisan vendor:publish --provider="ScriptDevelop\\WhatsappManager\\Providers\\WhatsappServiceProvider"</>');
        $io->write('');
        
        // Mensaje de apoyo
        $io->write('  <fg=yellow>2. Si este paquete te es útil, considera darle una estrella en GitHub.</>');
        $io->write('     <fg=yellow>Tu apoyo nos ayuda a crecer y mejorar.</>');
        $io->write('     <fg=blue;options=underscore>https://github.com/djdang3r/whatsapp-api-manager</>');
        $io->write('');
    }
}