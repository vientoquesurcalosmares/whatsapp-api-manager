<?php

return [
    'channels' => [
        'whatsapp' => [
            'driver' => 'daily',
            'path' => storage_path('logs/whatsapp.log'),
            'level' => 'debug',
            'days' => 7,
            'tap' => [\ScriptDevelop\WhatsappManager\Logging\CustomizeFormatter::class],
        ],
    ],
];