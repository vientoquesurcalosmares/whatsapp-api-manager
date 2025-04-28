<?php

namespace ScriptDevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;
use ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel;
use ScriptDevelop\WhatsappManager\Console\Commands\MergeLoggingConfig;
use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Fusionar configuración principal
        $this->mergeConfigFrom(__DIR__.'/../config/whatsapp.php', 'whatsapp');

        // Registrar cliente API principal
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('whatsapp.api.base_url', 'https://graph.facebook.com'),
                config('whatsapp.api.version', 'v19.0'),
                config('whatsapp.api.timeout', 30)
            );
        });

        // Servicio principal para operaciones generales
        $this->app->singleton('whatsapp.service', function ($app) {
            return new WhatsappService(
                $app->make(ApiClient::class),
                $app->make(WhatsappBusinessAccountRepository::class)
            );
        });

        // Servicio específico para envío de mensajes
        $this->app->singleton('whatsapp.message_dispatcher', function ($app) {
            return new MessageDispatcherService(
                $app->make(ApiClient::class)
            );
        });

        // Servicio de registro de cuentas
        $this->app->singleton('whatsapp.account', function ($app) {
            return new AccountRegistrationService(
                $app->make('whatsapp.service')
            );
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