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
        // 1. Fusionar configuración desde src/config
        $this->mergeConfigFrom(
            __DIR__.'/../config/whatsapp.php', // ✅ Ruta CORRECTA (src/config)
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
        // 1. Publicar migraciones desde src/database
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'), // ✅ Ruta CORRECTA
        ], 'whatsapp-migrations');

        // 2. Cargar migraciones automáticamente
        if (config('whatsapp.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations'); // ✅ Ruta CORRECTA
        }

        // 3. Publicar configuración desde src/config
        $this->publishes([
            __DIR__.'/../config/whatsapp.php' => config_path('whatsapp.php'), // ✅ Ruta CORRECTA
        ], 'whatsapp-config');

        // 4. Registrar comandos Artisan
        if (class_exists(\ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel::class)) {
            $this->commands([
                \ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel::class,
            ]);
        }
    }
}