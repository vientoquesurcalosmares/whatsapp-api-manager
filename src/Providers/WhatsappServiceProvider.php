<?php

namespace ScriptDevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register()
    {
        // 1. Fusionar configuración desde la raíz del paquete
        $this->mergeConfigFrom(
            __DIR__.'/../../config/whatsapp.php', // Ruta correcta
            'whatsapp'
        );

        // 2. Registrar el cliente API
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('whatsapp.api_url', 'https://graph.facebook.com'),
                config('whatsapp.api_version', 'v19.0'),
                config('whatsapp.timeout', 30)
            );
        });

        // 3. Registrar repositorio
        $this->app->singleton(WhatsappBusinessAccountRepository::class);

        // 4. Registrar servicio principal
        $this->app->singleton(WhatsappService::class, function ($app) {
            return new WhatsappService(
                $app->make(ApiClient::class),
                $app->make(WhatsappBusinessAccountRepository::class)
            );
        });
    }

    public function boot()
    {
        // 1. Publicar migraciones
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'whatsapp-migrations');

        // 2. Cargar migraciones automáticamente (si no se publican)
        if (config('whatsapp.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        // 3. Publicar configuración
        $this->publishes([
            __DIR__.'/../../config/whatsapp.php' => config_path('whatsapp.php'),
        ], 'whatsapp-config');

        // 4. Registrar comandos Artisan (si existen)
        if (class_exists(\ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel::class)) {
            $this->commands([
                \ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel::class,
            ]);
        }
    }
}