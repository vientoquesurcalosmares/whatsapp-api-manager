<?php

namespace ScriptDevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;
use ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel;
use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;

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

        // Registrar comandos de consola
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckUserModel::class,
                // No necesitas MergeLoggingConfig, salvo que quieras hacer la fusión opcional de logging
            ]);
        }
    }
}
