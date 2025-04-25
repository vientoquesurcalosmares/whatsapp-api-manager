<?php

namespace ScriptDevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;
use ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register()
    {
        // 1. Fusionar configuración
        $this->mergeConfigFrom(
            __DIR__.'/../config/whatsapp.php',
            'whatsapp'
        );

        // 2. Registrar el cliente API
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('whatsapp.api.url', 'https://graph.facebook.com'),
                config('whatsapp.api.version', 'v19.0'),
                config('whatsapp.api.timeout', 30)
            );
        });

        // 3. Registrar repositorio
        $this->app->singleton(WhatsappBusinessAccountRepository::class);

        // 4. Registrar servicio principal de mensajería
        $this->app->singleton('whatsapp.service', function ($app) {
            return new WhatsappService(
                $app->make(ApiClient::class),
                $app->make(WhatsappBusinessAccountRepository::class)
            );
        });

        // 5. Registrar servicio de cuentas
        $this->app->singleton('whatsapp.account', function ($app) {
            return new AccountRegistrationService(
                $app->make('whatsapp.service')
            );
        });
    }

    public function boot()
    {
        // Publicar migraciones
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'whatsapp-migrations');

        // Cargar migraciones automáticamente
        if (config('whatsapp.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Publicar configuración principal
        $this->publishes([
            __DIR__.'/../config/whatsapp.php' => config_path('whatsapp.php'),
        ], 'whatsapp-config');

        // Publicar configuración de logs (¡Nuevo!)
        $this->publishes([
            __DIR__.'/../config/logging.php' => config_path('logging.php'),
        ], 'whatsapp-logging');

        // Registrar comandos Artisan
        $this->commands([
            CheckUserModel::class,
        ]);
    }
}