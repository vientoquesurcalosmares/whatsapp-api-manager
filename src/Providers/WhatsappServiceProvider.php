<?php

namespace ScriptDevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;
use ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel;
use ScriptDevelop\WhatsappManager\Console\Commands\MergeLoggingConfig;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Fusionar configuración principal
        $this->mergeConfigFrom(
            __DIR__.'/../config/whatsapp.php',
            'whatsapp'
        );

        // Registrar cliente API
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('whatsapp.api.base_url', 'https://graph.facebook.com'),
                config('whatsapp.api.version', 'v19.0'),
                config('whatsapp.api.timeout', 30)
            );
        });

        // Registrar repositorio
        $this->app->singleton(WhatsappBusinessAccountRepository::class);
        
        // Registrar servicio principal
        $this->app->singleton('whatsapp.service', function ($app) {
            return new WhatsappService(
                $app->make(ApiClient::class),
                $app->make(WhatsappBusinessAccountRepository::class)
            );
        });

        // Registrar servicio de cuentas
        $this->app->singleton('whatsapp.account', function ($app) {
            return new AccountRegistrationService($app->make('whatsapp.service'));
        });
    }

    public function boot()
    {
        // Publicar solo configuraciones necesarias
        $this->publishes([
            __DIR__.'/../config/whatsapp.php' => config_path('whatsapp.php'),
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['whatsapp-config', 'whatsapp-migrations']);

        // Cargar migraciones condicionalmente
        if (config('whatsapp.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Registrar comandos
        $this->commands([
            CheckUserModel::class,
            MergeLoggingConfig::class
        ]);
    }
}