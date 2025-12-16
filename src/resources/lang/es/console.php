<?php

return [
    // CheckUserModel Command
    'check_user_model_description' => 'Verifica si el modelo User está configurado correctamente',
    'user_model_not_found' => 'El modelo User (:model) no existe.',
    'user_model_configured' => 'Modelo User configurado correctamente: :model',

    // MergeLoggingConfig Command
    'merge_logging_description' => 'Agrega el canal de logs de WhatsApp al archivo existente',
    'logging_file_not_found' => '❌ Archivo logging.php no encontrado en: :path',
    'error_modifying_config' => '❌ Error al modificar el archivo de configuración: :error',
    'channel_added_success' => '✅ Canal \'whatsapp\' agregado exitosamente a: :path',
    'channel_already_exists' => 'ℹ️ El canal \'whatsapp\' ya existe en: :path',
    'critical_error' => '🔥 Error crítico: :error',
];
