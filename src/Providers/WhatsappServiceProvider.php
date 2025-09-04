<?php

namespace ScriptDevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use ScriptDevelop\WhatsappManager\Services\WhatsappManager;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;
use ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel;
use ScriptDevelop\WhatsappManager\Services\BlockService;
use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;
use ScriptDevelop\WhatsappManager\Services\TemplateService;
use ScriptDevelop\WhatsappManager\Services\FlowService;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Fusionar configuraciones
        $this->mergeConfigFrom(__DIR__ . '/../Config/whatsapp.php', 'whatsapp');
        $this->mergeConfigFrom(__DIR__ . '/../Config/logging.php', 'logging');

        // Registrar servicios
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('whatsapp.api.base_url', 'https://graph.facebook.com'),
                config('whatsapp.api.version', 'v22.0'),
                config('whatsapp.api.timeout', 30)
            );
        });

        $this->app->singleton(WhatsappBusinessAccountRepository::class);

        $this->app->singleton(MessageDispatcherService::class, function ($app) {
            return new MessageDispatcherService(
                $app->make(ApiClient::class)
            );
        });

        $this->app->singleton('whatsapp.manager', function ($app) {
            return new WhatsappManager(
                $app->make(MessageDispatcherService::class)
            );
        });

        $this->app->singleton(AccountRegistrationService::class, function ($app) {
            return new AccountRegistrationService(
                $app->make(WhatsappService::class)
            );
        });

        $this->app->singleton(WhatsappService::class, function ($app) {
            return new WhatsappService(
                $app->make(ApiClient::class),
                $app->make(WhatsappBusinessAccountRepository::class)
            );
        });

        $this->app->singleton('whatsapp.manager', function ($app) {
            return new WhatsappManager(
                $app->make(MessageDispatcherService::class)
            );
        });

        $this->app->alias(WhatsappService::class, 'whatsapp.phone');
        $this->app->alias(MessageDispatcherService::class, 'whatsapp.message');
        $this->app->alias(AccountRegistrationService::class, 'whatsapp.account');

        $this->app->singleton('whatsapp.template', function ($app) {
            return new TemplateService(
                $app->make(ApiClient::class),
                $app->make(FlowService::class)
            );
        });

        $this->app->singleton('whatsapp.block', function ($app) {
            return new BlockService(
                $app->make(ApiClient::class)
            );
        });

        $this->app->singleton('whatsapp.flow', function ($app) {
            return new FlowService(
                $app->make(ApiClient::class)
            );
        });
    }

    public function boot()
    {
        // Publicar archivos de configuración
        $this->publishes([
            __DIR__ . '/../Config/whatsapp.php' => config_path('whatsapp.php'),
        ], 'whatsapp-config');

        // Publicar migraciones
        $this->publishes([
            __DIR__ . '/../Database/migrations' => database_path('migrations'),
        ], 'whatsapp-migrations');

        // Cargar automáticamente las migraciones si está habilitado
        if (config('whatsapp.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../Database/migrations');
        }

        // Publicar seeders
        $this->publishes([
            __DIR__ . '/../Database/seeders/WhatsappTemplateLanguageSeeder.php' => database_path('seeders/WhatsappTemplateLanguageSeeder.php'),
        ], 'whatsapp-seeders');

        // Publicar rutas
        $this->publishes([
            __DIR__ . '/../routes/whatsapp_webhook.php' => base_path('routes/whatsapp_webhook.php'),
        ], 'whatsapp-routes');

        $this->publishes([], 'whatsapp-media');

        $this->publishes([
            __DIR__ . '/../routes/channels.php' => base_path('routes/channels.php'),
        ], 'whatsapp-events');

        // Cargar rutas automáticamente
        $this->loadRoutesFrom(__DIR__ . '/../routes/whatsapp_webhook.php');

        // Registrar comandos de consola
        if ($this->app->runningInConsole()) {
            // Crear directorios necesarios al publicar configuraciones
            $this->publishes([], 'whatsapp-storage');

            $this->publishes([
                __DIR__ . '/../Database/seeders/WhatsappTemplateLanguageSeeder.php' => database_path('seeders/WhatsappTemplateLanguageSeeder.php'),
            ], 'whatsapp-seeders');

            $this->commands([
                CheckUserModel::class,
            ]);
        }

        // Crear el enlace simbólico y directorios solo al publicar configuraciones
        if ($this->app->runningInConsole() && $this->isPublishing()) {
            $this->createStorageDirectories();
            $this->createStorageLink();
        }

        if ($this->app->runningInConsole()) {
            if (!file_exists(config_path('whatsapp.php'))) {
                $this->app->booted(function () {
                    $output = new \Symfony\Component\Console\Output\ConsoleOutput();

                    // --- MENSAJE CON FORMATO MEJORADO ---

                    // Espacio en blanco superior para separar del output de Composer
                    $output->writeln('');

                    // Borde superior verde
                    $output->writeln('  <bg=green;fg=white> SUCCESS </> <fg=green>WhatsApp API Manager instalado correctamente.</>');
                    
                    // Espacio
                    $output->writeln('');

                    // Cuerpo del mensaje en amarillo (comentario)
                    $output->writeln('  <fg=yellow>🎉 ¡Gracias por elegir nuestro paquete! 🎉</>');
                    
                    // Espacio
                    $output->writeln('');

                    // Instrucciones con el comando resaltado en cian
                    $output->writeln('  <options=bold>Siguientes Pasos:</>');
                    $output->writeln('  <fg=yellow>1. Publica los archivos de configuración y migraciones ejecutando:</>');
                    $output->writeln('     <fg=cyan>php artisan vendor:publish --provider="ScriptDevelop\WhatsappManager\Providers\WhatsappServiceProvider"</>');
                    
                    // Espacio
                    $output->writeln('');

                    // Mensaje de apoyo con el enlace formateado
                    $output->writeln('  <fg=yellow>2. Si este paquete te es útil, considera darle una estrella en GitHub.</>');
                    $output->writeln('     <fg=yellow>Tu apoyo nos ayuda a crecer y mejorar.</>');
                    
                    // Enlace formateado para que parezca un link clickeable
                    $output->writeln('     <fg=blue;options=underscore>https://github.com/djdang3r/whatsapp-api-manager</>');
                    
                    // Espacio en blanco inferior
                    $output->writeln('');
                });
            }
        }
    }

    /**
     * Crear los directorios necesarios para el almacenamiento.
     */
    protected function createStorageDirectories()
    {
        $basePath = storage_path('app/public/whatsapp');
        $folders = ['audios', 'documents', 'images', 'stickers', 'videos'];

        foreach ($folders as $folder) {
            $path = "{$basePath}/{$folder}";
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->app['log']->info("Directorio creado: {$path}");
            }
        }
    }

    /**
     * Crear el enlace simbólico para storage.
     */
    protected function createStorageLink()
    {
        $mediaBasePath = storage_path('app/public/whatsapp');
        $mediaLinkPath = public_path('storage/whatsapp');

        try {
            // Asegurar directorio padre
            $parentDir = dirname($mediaLinkPath);
            if (!is_dir($parentDir) && !@mkdir($parentDir, 0755, true) && !is_dir($parentDir)) {
                throw new \RuntimeException("No se pudo crear el directorio: {$parentDir}");
            }

            // Crear enlace solo si no existe
            if (!file_exists($mediaLinkPath)) {
                if (@symlink($mediaBasePath, $mediaLinkPath)) {
                    $this->app['log']->info('Enlace simbólico creado exitosamente.');
                } else {
                    $this->app['log']->warning("Falló la creación automática del enlace. Ejecuta manualmente: php artisan storage:link");
                }
            }
        } catch (\Throwable $e) {
            $this->app['log']->error("Error en storage link: {$e->getMessage()}");
            $this->app['log']->warning("El paquete se instaló correctamente, pero debes ejecutar MANUALMENTE: php artisan storage:link");
        }
    }

    /**
     * Verifica si se está ejecutando una publicación.
     */
    protected function isPublishing(): bool
    {
        $argv = request()->server('argv', []);
        return in_array('--tag=whatsapp-config', $argv) ||
            in_array('--tag=whatsapp-migrations', $argv) ||
            in_array('--tag=whatsapp-routes', $argv) ||
            in_array('--tag=whatsapp-storage', $argv);
    }
}