<?php

namespace ScriptDevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;
use ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel;
use ScriptDevelop\WhatsappManager\Services\BotBuilderService;
use ScriptDevelop\WhatsappManager\Services\FlowBuilderService;
use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;
use ScriptDevelop\WhatsappManager\Services\TemplateService;

use Illuminate\Support\Facades\Artisan;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Fusionar configuraciones
        $this->mergeConfigFrom(__DIR__ . '/../config/whatsapp.php', 'whatsapp');
        $this->mergeConfigFrom(__DIR__ . '/../config/logging.php', 'logging');

        // Registrar servicios
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('whatsapp.api.base_url', 'https://graph.facebook.com'),
                config('whatsapp.api.version', 'v19.0'),
                config('whatsapp.api.timeout', 30)
            );
        });

        $this->app->singleton(WhatsappBusinessAccountRepository::class);

        $this->app->singleton('whatsapp.manager', function($app){
            return new \ScriptDevelop\WhatsappManager\Services\WhatsappManager;
        });

        $this->app->singleton('whatsapp.phone', function ($app) {
            return new WhatsappService(
                $app->make(ApiClient::class),
                $app->make(WhatsappBusinessAccountRepository::class)
            );
        });

        $this->app->singleton('whatsapp.message', function ($app) {
            return new MessageDispatcherService(
                $app->make(ApiClient::class)
            );
        });

        $this->app->singleton('whatsapp.account', function ($app) {
            return new AccountRegistrationService(
                $app->make('whatsapp.phone')
            );
        });

        // Registrar el servicio de plantillas
        $this->app->singleton('whatsapp.template', function ($app) {
            return new TemplateService(
                $app->make(ApiClient::class)
            );
        });

        // Registrar el BotBuilderService
        $this->app->singleton('whatsapp.bot', function ($app) {
            return new BotBuilderService();
        });

        // Registrar el FlowBuilderService
        $this->app->singleton('whatsapp.flow', function ($app) {
            return new FlowBuilderService();
        });
    }

    public function boot()
    {
        // Publicar archivos de configuración
        $this->publishes([
            __DIR__ . '/../config/whatsapp.php' => config_path('whatsapp.php'),
        ], 'whatsapp-config');

        // Publicar migraciones
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'whatsapp-migrations');

        // Cargar automáticamente las migraciones si está habilitado
        if (config('whatsapp.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        // Publicar rutas
        $this->publishes([
            __DIR__ . '/../routes/whatsapp_webhook.php' => base_path('routes/whatsapp_webhook.php'),
        ], 'whatsapp-routes');

        // Cargar rutas automáticamente
        $this->loadRoutesFrom(__DIR__ . '/../routes/whatsapp_webhook.php');

        // Registrar comandos de consola
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckUserModel::class,
            ]);

            // Crear directorios necesarios al publicar configuraciones
            $this->publishes([], 'whatsapp-storage');
        }

        // Crear el enlace simbólico y directorios solo al publicar configuraciones
        if ($this->app->runningInConsole() && $this->isPublishing()) {
            $this->createStorageDirectories();
            $this->createStorageLink();
        }
    }

    /**
     * Crear los directorios necesarios para el almacenamiento.
     */
    protected function createStorageDirectories()
    {
        $directories = config('whatsapp.media.storage_path', []);

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
                $this->app['log']->info("Directorio creado: {$directory}");
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