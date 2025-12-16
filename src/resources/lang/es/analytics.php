<?php

return [
    // Command Description and Options
    'description' => 'Obtiene analytics de templates de WhatsApp Business desde la API de Meta para una, varias o todas las cuentas según las opciones proporcionadas',

    'option_force' => 'Forzar obtención de 90 días incluso si hay datos',
    'option_template' => 'Obtener analytics para templates específicos (puede usarse múltiples veces)',
    'option_days' => 'Número específico de días a obtener (máximo 90)',
    'option_account' => 'Procesar cuentas específicas (puede usarse múltiples veces)',
    'option_show_errors' => 'Mostrar logs de error durante la ejecución',
    'option_show_info' => 'Mostrar logs de información durante la ejecución',
    'option_show_warning' => 'Mostrar logs de advertencia durante la ejecución',

    // Process Messages
    'starting' => 'Iniciando obtención de analytics de templates de WhatsApp Business...',
    'no_accounts_found' => 'No se encontraron cuentas de WhatsApp Business para procesar',
    'processing_accounts' => 'Procesando <fg=blue>:count</> cuenta(s) de WhatsApp Business',
    'analyzing_period' => 'Obteniendo analytics de los últimos <fg=blue>:days</> días (desde <fg=blue>:start</> hasta <fg=blue>:end</>)',
    'processing_account' => 'Procesando cuenta: <fg=blue>:id | :name</>',
    'account_processed' => 'Cuenta procesada: <fg=blue>:processed</> procesados, <fg=blue>:saved</> guardados, <fg=blue>:skipped</> omitidos (porque sus valores son 0), <fg=blue>:errors</> errores',
    'account_error' => 'Error procesando cuenta: <fg=blue>:error</>',
    'pause_between_accounts' => 'Pausa de <fg=blue>3</> segundos entre cuentas...',

    // Summary
    'process_completed' => 'Proceso completado:',
    'accounts_processed' => 'Cuentas procesadas: <fg=blue>:processed/:total</>',
    'records_processed' => 'Registros procesados: <fg=blue>:count</>',
    'records_saved' => 'Registros guardados: <fg=blue>:count</>',
    'records_skipped' => 'Registros omitidos (porque sus valores son 0): <fg=blue>:count</>',
    'total_errors' => 'Errores totales: <fg={color}>:count</>',

    // Errors
    'general_error' => 'Error general: :message',
    'no_accounts_with_ids' => 'No se encontraron cuentas válidas con los IDs: <fg=blue>:ids</>',

    // Account Processing
    'specific_accounts' => '<fg=blue>:count</> cuenta(s) específica(s): :ids',
    'found_valid_accounts' => 'Encontradas <fg=blue>:count</> cuenta(s) válida(s) con token configurado',
    'found_accounts_with_token' => 'Encontradas <fg=blue>:count</> cuentas con token configurado',
    'api_client_setup_failed' => 'No se pudo configurar el cliente API',
    'no_templates_found' => 'No se encontraron templates para esta cuenta',
    'processing_templates' => 'Procesando <fg=blue>:count</> templates en chunks de <fg=blue>10</>',
    'processing_chunk' => 'Chunk <fg=blue>:current</>/<fg=blue>:total</>',
    'api_token_not_configured' => 'Token de API no configurado en la cuenta',

    // Template Filtering
    'filtering_templates' => 'Filtrando por <fg=blue>:count</> template(s) específico(s): <fg=blue>:ids</>',

    // Days Determination
    'days_specified' => 'Días especificados manualmente: <fg={color}>:input</> (máximo permitido: <fg=blue>90</>)',
    'forced_mode' => 'Modo forzado: obteniendo <fg=blue>90</> días',
    'empty_table' => '<fg=yellow>Tabla vacía:</> obteniendo <fg=blue>90</> días iniciales',
    'update_mode' => 'Tabla con datos: obteniendo <fg=blue>7</> días para actualización',

    // Chunk Processing
    'no_data_for_chunk' => 'No se pudieron obtener datos para este chunk',
    'error_saving_template' => 'Error guardando template <fg=blue>:id</>: :message',
    'chunk_processing_error' => 'Error procesando chunk: :message',

    // API Communication
    'api_response_code' => 'API respondió con código: <fg=blue>:code</>',
    'api_connection_error' => 'Error de conexión con la API: :message',
    'api_error_response' => 'Respuesta de error: :body',
];
