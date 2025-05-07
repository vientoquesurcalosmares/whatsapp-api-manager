<?php

return [

    'models' => [
        'business_account' => \ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount::class,
        'user_model' => env('AUTH_MODEL', App\Models\User::class),
        'user_table' => env('AUTH_TABLE', 'users'),
    ],
    'api' => [
        'base_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'),
        'version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'timeout' => env('WHATSAPP_API_TIMEOUT', 30),
        'retry' => [
            'attempts' => 3,
            'delay' => 500,
        ],
    ],
    'webhook' => [
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],
    'media' => [
        'max_file_size' => [
            'image' => 5 * 1024 * 1024, // 5MB
            'audio' => 16 * 1024 * 1024, // 16MB
            'video' => 16 * 1024 * 1024, // 16MB
            'document' => 100 * 1024 * 1024, // 100MB
            'sticker' => 100 * 1024, // 100KB
        ],
        'allowed_types' => [
            'image' => ['image/jpeg', 'image/png'],
            'audio' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'],
            'video' => ['video/mp4', 'video/3gp'],
            'document' => [
                'text/plain',
                'application/pdf',
                'application/vnd.ms-powerpoint',
                'application/msword',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'sticker' => ['image/webp'],
        ],
    ],
    'load_migrations' => true, // Control para migraciones automáticas
];
