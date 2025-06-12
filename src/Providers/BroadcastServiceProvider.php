<?php

namespace Scriptdevelop\WhatsappManager\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (file_exists(config_path('whatsapp-events.php')) && config('whatsapp-events.custom_channels')) {
            require base_path('routes/channels.php');
        } else {
            require __DIR__ . '/../routes/channels.php';
        }
    }
}
