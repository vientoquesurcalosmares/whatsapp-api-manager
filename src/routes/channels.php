<?php

use Illuminate\Support\Facades\Broadcast;

if (config('whatsapp.broadcast_channel_type') === 'private') {

    Broadcast::channel('whatsapp-messages', function ($user) {
        // Puedes personalizar la lógica de acceso aquí
        return $user !== null;
    });

    Broadcast::channel('whatsapp-outgoing', function ($user) {
        return Auth::check();
    });

    Broadcast::channel('whatsapp-status', function ($user) {
        return Auth::check();
    });

    Broadcast::channel('whatsapp-contacts', function ($user) {
        return Auth::check();
    });

    Broadcast::channel('whatsapp-templates', function ($user) {
        return Auth::check();
    });

    Broadcast::channel('whatsapp-business', function ($user) {
        return Auth::check();
    });
}