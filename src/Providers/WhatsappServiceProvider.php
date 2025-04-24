<?php

namespace ScriptDevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use ScriptDevelop\WhatsappManager\Services\AccountService;
use ScriptDevelop\WhatsappManager\Services\MessageService;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('whatsapp.account', fn ($app) => new AccountService());
        // $this->app->singleton('whatsapp.message', fn ($app) => new MessageService());
        
        $this->mergeConfigFrom(__DIR__.'/../../config/whatsapp.php', 'whatsapp');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/whatsapp.php' => config_path('whatsapp.php'),
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'whatsapp-manager');
    }
}