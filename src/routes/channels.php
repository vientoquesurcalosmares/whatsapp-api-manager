<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('whatsapp.messages', function ($user) {
    return Auth::check();
});

Broadcast::channel('whatsapp.outgoing', function ($user) {
    return Auth::check();
});

Broadcast::channel('whatsapp.status', function ($user) {
    return Auth::check();
});

Broadcast::channel('whatsapp.contacts', function ($user) {
    return Auth::check();
});

Broadcast::channel('whatsapp.templates', function ($user) {
    return Auth::check();
});

Broadcast::channel('whatsapp.business', function ($user) {
    return Auth::check();
});