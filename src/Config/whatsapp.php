<?php

return [
    'api' => [
        'base_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'),
        'version' => env('WHATSAPP_API_VERSION', 'v19.0'),
        'timeout' => env('WHATSAPP_API_TIMEOUT', 30),
    ],
    'models' => [
        'business_account' => \ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount::class,
        'user_model' => env('AUTH_MODEL', App\Models\User::class),
    ],
];