<?php

return [
    // CheckUserModel Command
    'check_user_model_description' => 'Verify if the User model is configured correctly',
    'user_model_not_found' => 'The User model (:model) does not exist.',
    'user_model_configured' => 'User model configured correctly: :model',

    // MergeLoggingConfig Command
    'merge_logging_description' => 'Add WhatsApp logs channel to existing file',
    'logging_file_not_found' => '❌ logging.php file not found in: :path',
    'error_modifying_config' => '❌ Error modifying configuration file: :error',
    'channel_added_success' => '✅ \'whatsapp\' channel added successfully to: :path',
    'channel_already_exists' => 'ℹ️ The \'whatsapp\' channel already exists in: :path',
    'critical_error' => '🔥 Critical error: :error',
];
