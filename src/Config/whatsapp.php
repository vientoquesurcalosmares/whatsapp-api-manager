<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model Class
    |--------------------------------------------------------------------------
    | Modelo de usuario predeterminado de Laravel.
    */
    'user_model' => env('WHATSAPP_USER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp API Base URL
    |--------------------------------------------------------------------------
    | Ejemplo: https://graph.facebook.com/ (sin versión)
    */
    'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp API Version
    |--------------------------------------------------------------------------
    | Versión de la API. Ejemplo: v19.0
    */
    'api_version' => env('WHATSAPP_API_VERSION', 'v19.0'),

    /*
    |--------------------------------------------------------------------------
    | Default Timeout
    |--------------------------------------------------------------------------
    | Tiempo máximo (segundos) para esperar respuesta de la API.
    */
    'timeout' => env('WHATSAPP_API_TIMEOUT', 30),
];