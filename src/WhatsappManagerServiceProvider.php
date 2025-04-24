<?php

namespace ScriptDevelop\WhatsappManager;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\WhatsappService;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;

class WhatsappManagerServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Fusionar configuración (solo si existe el archivo)
        if (file_exists(__DIR__.'/Config/whatsapp.php')) {
            $this->mergeConfigFrom(
                __DIR__.'/Config/whatsapp.php', 
                'whatsapp'
            );
        }

        // Registrar el cliente API
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('whatsapp.api_url', 'https://graph.facebook.com'), // Valor por defecto
                config('whatsapp.api_version', 'v19.0'), // Valor por defecto
                config('whatsapp.timeout', 30) // Valor por defecto
            );
        });

        // Registrar el repositorio
        $this->app->singleton(WhatsappBusinessAccountRepository::class);

        // Registrar el servicio principal
        $this->app->singleton(WhatsappService::class, function ($app) {
            return new WhatsappService(
                $app->make(ApiClient::class),
                $app->make(WhatsappBusinessAccountRepository::class)
            );
        });
    }

    public function boot()
    {
        // ========== CORRECCIONES PRINCIPALES ========== //
        
        // 1. Publicar migraciones con tag específico
        $this->publishes([
            __DIR__.'/Database/Migrations' => database_path('migrations'),
        ], 'whatsapp-migrations'); // Tag modificado

        // 2. Cargar migraciones solo si no se publican
        if (config('whatsapp.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        }

        // Publicar configuración
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            
            // Registrar comandos solo si existen
            if (class_exists(\ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel::class)) {
                $this->commands([
                    \ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel::class,
                ]);
            }
        }
    }

    protected function publishConfig()
    {
        // Publicar configuración solo si el archivo existe
        if (file_exists(__DIR__.'/Config/whatsapp.php')) {
            $this->publishes([
                __DIR__.'/Config/whatsapp.php' => config_path('whatsapp.php'),
            ], 'whatsapp-config'); // Tag único para configuración
        }
    }
}