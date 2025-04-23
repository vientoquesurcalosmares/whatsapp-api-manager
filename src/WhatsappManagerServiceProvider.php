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
        // Fusionar configuración
        $this->mergeConfigFrom(
            __DIR__.'/Config/whatsapp.php', 
            'whatsapp'
        );

        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                config('whatsapp.api_url'),
                config('whatsapp.api_version'),
                config('whatsapp.timeout')
            );
        });

        $this->app->singleton(WhatsappBusinessAccountRepository::class);

        $this->app->singleton(WhatsappService::class, function ($app): WhatsappService {
            return new WhatsappService(
                $app->make(ApiClient::class), // Inyecta ApiClient
                $app->make(WhatsappBusinessAccountRepository::class) // Inyecta el repositorio
            );
        });
    }

    public function boot()
    {
        // Cargar migraciones
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // Publicar configuración (solo si la aplicación host es Laravel)
        if ($this->app->runningInConsole()) {
            $this->publishConfig();

            $this->commands([
                \ScriptDevelop\WhatsappManager\Console\Commands\CheckUserModel::class,
            ]);
        }
    }

    protected function publishConfig()
    {
        // Método compatible con Laravel y paquetes independientes
        $configPath = $this->app->bound('path.config') 
            ? $this->app->make('path.config') 
            : (function_exists('config_path') ? config_path() : __DIR__.'/../../../config');

        $this->publishes([
            __DIR__.'/Config/whatsapp.php' => $configPath.'/whatsapp.php',
        ], 'whatsapp-config');
    }
}