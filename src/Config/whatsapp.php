<?php

return [

    'api' => [
        'base_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'),
        'version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'timeout' => env('WHATSAPP_API_TIMEOUT', 30),
        'retry' => [
            'attempts' => 3,
            'delay' => 500,
        ],
    ],
    'media' => [
        'max_file_size' => [
            'image' => 5 * 1024 * 1024, // 5MB
            'document' => 100 * 1024 * 1024, // 100MB
        ],
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
];
