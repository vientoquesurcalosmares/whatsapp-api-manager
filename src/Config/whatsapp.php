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
        'user_table' => env('AUTH_TABLE', 'users'),
    ],
    'webhook' => [
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],
    'load_migrations' => true, // Control para migraciones automáticas
    'sync_on_query' => env('WHATSAPP_SYNC_ON_QUERY', false),
];