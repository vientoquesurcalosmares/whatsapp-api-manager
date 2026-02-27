<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modelos Personalizados
    |--------------------------------------------------------------------------
    |
    | Aquí puedes especificar los modelos que el paquete utilizará para las
    | entidades principales. Puedes sobrescribir estos valores en tu archivo
    | .env si estás utilizando modelos personalizados.
    |
    */
    'models' => [
        //Contactos
        'contact' => \ScriptDevelop\WhatsappManager\Models\Contact::class,

        'contact_profile' => \ScriptDevelop\WhatsappManager\Models\WhatsappContactProfile::class,

        'conversation' => \ScriptDevelop\WhatsappManager\Models\Conversation::class,

        //Archivos multimedia
        'media_file' => \ScriptDevelop\WhatsappManager\Models\MediaFile::class,

        //Mensajes
        'message' => \ScriptDevelop\WhatsappManager\Models\Message::class,

        //Plantillas
        'template' => \ScriptDevelop\WhatsappManager\Models\Template::class,

        //Categorías de Plantillas
        'template_category' => \ScriptDevelop\WhatsappManager\Models\TemplateCategory::class,

        //Componentes de Plantillas
        'template_component' => \ScriptDevelop\WhatsappManager\Models\TemplateComponent::class,

        //Idiomas de Plantillas
        'template_language' => \ScriptDevelop\WhatsappManager\Models\TemplateLanguage::class,

        //Versiones de plantillas
        'template_version' => \ScriptDevelop\WhatsappManager\Models\TemplateVersion::class,

        //Template analytics
        'general_template_analytics' => \ScriptDevelop\WhatsappManager\Models\GeneralTemplateAnalytics::class,

        //Template analytics clicked
        'general_template_analytics_clicked' => \ScriptDevelop\WhatsappManager\Models\GeneralTemplateAnalyticsClicked::class,

        //Template analytics cost
        'general_template_analytics_cost' => \ScriptDevelop\WhatsappManager\Models\GeneralTemplateAnalyticsCost::class,

        'website' => \ScriptDevelop\WhatsappManager\Models\Website::class,

        // Modelo para la cuenta empresarial de WhatsApp
        'business_account' => \ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount::class,

        //Perfil de la cuenta de Whatsapp
        'business_profile' => \ScriptDevelop\WhatsappManager\Models\WhatsappBusinessProfile::class,

        'flow' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlow::class,

        'flow_event' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlowEvent::class,

        'flow_response' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlowResponse::class,

        'flow_screen' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlowScreen::class,

        'flow_session' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlowSession::class,

        //Números de celular configurados en Whatsapp Business
        'phone_number' => \ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber::class,

        'screen_element' => \ScriptDevelop\WhatsappManager\Models\WhatsappScreenElement::class,

        'template_flow' => \ScriptDevelop\WhatsappManager\Models\WhatsappTemplateFlow::class,

        'blocked_user' => \ScriptDevelop\WhatsappManager\Models\BlockedUser::class,

        // Modelo de usuario (puede ser personalizado)
        'user_model' => env('AUTH_MODEL', 'App\Models\User::class'),

        // Tabla de usuarios (puede ser personalizada)
        //'user_table' => env('AUTH_TABLE', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eventos Personalizados
    |--------------------------------------------------------------------------
    |
    | Aquí puedes especificar los eventos que el paquete utilizará.
    | Puedes cambiar estas clases por personalizadas.
    |
    */
    'events' => [
        //Este evento se dispara cuando se actualiza
        'account' => [
            'status_updated' => \Scriptdevelop\WhatsappManager\Events\AccountStatusUpdated::class,
        ],
        'business' => [
            'settings' => [
                'updated' => \Scriptdevelop\WhatsappManager\Events\BusinessSettingsUpdated::class, //Aun no se implementa
            ],
        ],
        'contact' => [
            'created' => \Scriptdevelop\WhatsappManager\Events\ContactCreated::class, //Aun no se implementa
            'updated' => \Scriptdevelop\WhatsappManager\Events\ContactUpdated::class, //Aun no se implementa
        ],
        'messages' => [
            'contact' => [
                /**
                 * Se dispara cuando se envía al webhook mensaje de recibido de tipo contacto, recibe como parámetro el objeto de contacto y el objeto de mensaje
                 */
                'received' => \Scriptdevelop\WhatsappManager\Events\ContactMessageReceived::class,
            ],
            'interactive' => [
                /**
                 * Se dispara cuando se envía al webhook mensaje de recibido de tipo interactive, recibe como parámetro el objeto de contacto y el objeto de mensaje
                 */
                'received' => \Scriptdevelop\WhatsappManager\Events\InteractiveMessageReceived::class,
            ],
            'location' => [
                /**
                 * Se dispara cuando se envía al webhook mensaje de recibido de tipo location, recibe como parámetro el objeto de contacto y el objeto de mensaje
                 */
                'received' => \Scriptdevelop\WhatsappManager\Events\LocationMessageReceived::class,
            ],
            'media' => [
                /**
                 * Se dispara cuando se envía al webhook mensaje de recibido de tipo image, audio, video, document o sticker, recibe como parámetro el objeto de contacto y el objeto de mensaje
                 */
                'received' => \Scriptdevelop\WhatsappManager\Events\MediaMessageReceived::class,
            ],
            'message' => [
                'deleted' => \Scriptdevelop\WhatsappManager\Events\MessageDeleted::class, //Aun no se implementa
                /**
                 * Se dispara cuando se envía al webhook mensaje de entregado de tipo message, recibe como parámetro el objeto de mensaje
                 */
                'delivered' => \Scriptdevelop\WhatsappManager\Events\MessageDelivered::class,
                /**
                 * Se dispara cuando se envía al webhook mensaje de falla de tipo message, recibe como parámetro el objeto de mensaje
                 */
                'failed' => \Scriptdevelop\WhatsappManager\Events\MessageFailed::class,
                /**
                 * Se dispara cuando se envía al webhook mensaje de leído de tipo message, recibe como parámetro el objeto de mensaje
                 */
                'read' => \Scriptdevelop\WhatsappManager\Events\MessageRead::class,
                /**
                 * Se dispara cuando se envía al webhook mensaje de recibido de tipo message, recibe como parámetro el objeto de mensaje
                 */
                'received' => \Scriptdevelop\WhatsappManager\Events\MessageReceived::class,
                'sent' => \Scriptdevelop\WhatsappManager\Events\MessageSent::class, //No implementado en webhook
                'marketing_opt_out' => \Scriptdevelop\WhatsappManager\Events\WhatsappMarketingOptOut::class,
                'system' => [
                    'received' => \Scriptdevelop\WhatsappManager\Events\SystemMessageReceived::class,
                ],
                'edited'    => \Scriptdevelop\WhatsappManager\Events\MessageEdited::class,
                'revoked'   => \Scriptdevelop\WhatsappManager\Events\MessageRevoked::class,
            ],
            'reaction' => [
                /**
                 * Se dispara cuando se envía al webhook mensaje de recibido de tipo reaction, recibe como parámetro el objeto de contacto y el objeto de mensaje
                 */
                'received' => \Scriptdevelop\WhatsappManager\Events\ReactionReceived::class,
            ],
            'text' => [
                /**
                 * Se dispara cuando se envía al webhook mensaje de recibido de tipo text, recibe como parámetro el objeto de contacto y el objeto de mensaje
                 */
                'received' => \Scriptdevelop\WhatsappManager\Events\TextMessageReceived::class,
            ],
            'unsupported' => [
                /**
                 * Se dispara cuando se envía al webhook mensaje de recibido de tipo unsupported, recibe como parámetro el objeto de contacto y el objeto de mensaje
                 */
                'received' => \Scriptdevelop\WhatsappManager\Events\TextMessageReceived::class,
            ],
        ],
        'phone_number' => [
            'updated' => \Scriptdevelop\WhatsappManager\Events\PhoneNumberStatusUpdated::class, //Aun no se implementa
            'name_updated' => \Scriptdevelop\WhatsappManager\Events\PhoneNumberNameUpdated::class,
            'quality_updated' => \Scriptdevelop\WhatsappManager\Events\PhoneNumberQualityUpdated::class,
        ],
        'template' => [
            'approved' => \Scriptdevelop\WhatsappManager\Events\TemplateApproved::class, //Aun no se implementa
            'created' => \Scriptdevelop\WhatsappManager\Events\TemplateCreated::class, //Aun no se implementa
            'sent' => \Scriptdevelop\WhatsappManager\Events\TemplateMessageSent::class, //Aun no se implementa
            'rejected' => \Scriptdevelop\WhatsappManager\Events\TemplateRejected::class, //Aun no se implementa
        ],
        'coexistence' => [
            'history_synced' => \ScriptDevelop\WhatsappManager\Events\CoexistenceHistorySynced::class,
            'contact_synced' => \ScriptDevelop\WhatsappManager\Events\CoexistenceContactSynced::class,
            'smb_message_echo' => \ScriptDevelop\WhatsappManager\Events\CoexistenceSmbMessageEcho::class,
            'account_updated' => \ScriptDevelop\WhatsappManager\Events\CoexistenceAccountUpdated::class,
        ],
        'partner' => [
            'app_installed' => \ScriptDevelop\WhatsappManager\Events\PartnerAppInstalled::class,
            'app_uninstalled' => \ScriptDevelop\WhatsappManager\Events\PartnerAppUninstalled::class,
            'partner_added' => \ScriptDevelop\WhatsappManager\Events\PartnerAdded::class,
            'partner_removed' => \ScriptDevelop\WhatsappManager\Events\PartnerRemoved::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de la API de WhatsApp
    |--------------------------------------------------------------------------
    |
    | Configuración principal para interactuar con la API de WhatsApp Business.
    | Incluye la URL base, la versión de la API, el tiempo de espera y las
    | opciones de reintento en caso de errores.
    |
    */
    'api' => [
        // URL base de la API de WhatsApp
        'base_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'),

        // Versión de la API de WhatsApp
        //Advertencia: Cuidado al cambiar la versión, puede afectar a las funcionalidades y generar errores si versiones futuras cambian la estructura de los endpoints
        // Asegúrate de que la versión sea compatible con tu implementación actual.
        'version' => env('WHATSAPP_API_VERSION', 'v22.0'),

        // Tiempo de espera para las solicitudes (en segundos)
        'timeout' => env('WHATSAPP_API_TIMEOUT', 30),

        // Configuración de reintentos en caso de errores
        'retry' => [
            'attempts' => 3, // Número de intentos
            'delay' => 500, // Tiempo de espera entre intentos (en milisegundos)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración del Webhook
    |--------------------------------------------------------------------------
    |
    | Configuración para el webhook de WhatsApp. Incluye el token de verificación
    | que se utiliza para validar las solicitudes entrantes desde Meta.
    |
    */
    'webhook' => [
        // Token de verificación para el webhook
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

        // Procesador personalizado para webhooks (valor por defecto)
        'processor' => \ScriptDevelop\WhatsappManager\Services\WebhookProcessors\BaseWebhookProcessor::class,

        // Campos suscritos por defecto para los webhooks (ordenados alfabéticamente)
        'subscribed_fields' => [
            // 'account_alerts',                                        // Alertas de cuenta
            // 'account_review_update',                                 // Actualización de revisión de cuenta
            // 'account_settings_update',                               // Actualización de configuración de cuenta
            // 'account_update',                                        // Actualización de cuenta
            // 'automatic_events',                                      // Eventos automáticos
            // 'business_capability_update',                            // Actualización de capacidades de negocio
            // 'business_status_update',                                // Actualización de estado de negocio
            // 'calls',                                                 // Llamadas
            // 'flows',                                                 // Flujos de WhatsApp
            // 'history',                                               // Historial
            // 'message_template_components_update',                    // Actualización de componentes de plantilla de mensaje
            'message_template_quality_update',                          // Actualización de calidad de plantilla de mensaje
            'message_template_status_update',                           // Actualización de estado de plantilla de mensaje
            'messages',                                                 // Mensajes
            // 'partner_solutions',                                     // Soluciones de socios
            // 'payment_configuration_update',                          // Actualización de configuración de pagos
            // 'phone_number_name_update',                              // Actualización de nombre de número de teléfono
            'phone_number_quality_update',                              // Actualización de calidad de número de teléfono
            // 'security',                                              // Seguridad
            // 'smb_app_state_sync',                                    // Sincronización de estado de la app SMB
            // 'smb_message_echoes',                                    // Eco de mensajes SMB
            // 'template_category_update',                              // Actualización de categoría de plantilla
            // 'template_correct_category_detection',                   // Detección correcta de categoría de plantilla
            // 'tracking_events',                                       // Eventos de seguimiento
            // 'user_preferences',                                      // Preferencias de usuario
            // 'message_echoes',                                        // Eco de mensajes
            // 'messaging_handovers',                                   // Transferencias de mensajería
            // 'group_lifecycle_update',                                // Actualización del ciclo de vida de grupo        // v24.0
            // 'group_participants_update',                             // Actualización de participantes de grupo      // v24.0
            // 'group_settings_update',                                 // Actualización de configuración de grupo          // v24.0
            // 'group_status_update',                                   // Actualización de estado de grupo                   // v24.0
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Medios
    |--------------------------------------------------------------------------
    |
    | Configuración para la gestión de archivos multimedia. Incluye los tamaños
    | máximos permitidos y los tipos MIME aceptados para cada tipo de archivo.
    |
    */
    'media' => [
        // Directorios de almacenamiento para cada tipo de archivo
        'storage_path' => [
            'images' => storage_path('app/public/whatsapp/images'),
            'audios' => storage_path('app/public/whatsapp/audios'),
            'documents' => storage_path('app/public/whatsapp/documents'),
            'videos' => storage_path('app/public/whatsapp/videos'),
            'stickers' => storage_path('app/public/whatsapp/stickers'),
        ],
        // Tamaño máximo permitido para cada tipo de archivo (en bytes)
        'max_file_size' => [
            'image' => 5 * 1024 * 1024, // 5MB
            'audio' => 16 * 1024 * 1024, // 16MB
            'video' => 16 * 1024 * 1024, // 16MB
            'document' => 100 * 1024 * 1024, // 100MB
            'sticker' => 100 * 1024, // 100KB
        ],

        // Tipos MIME permitidos para cada tipo de archivo
        // Advertencia: Asegúrate de que los tipos MIME coincidan con los que WhatsApp acepta.
        'allowed_types' => [
            'image' => ['image/jpeg', 'image/png'], // Imágenes
            'audio' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'], // Audios
            'video' => ['video/mp4', 'video/3gp'], // Videos
            'document' => [ // Documentos
                'text/plain',
                'application/pdf',
                'application/vnd.ms-powerpoint',
                'application/msword',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'sticker' => ['image/webp'], // Stickers
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Personalización de canales
    |--------------------------------------------------------------------------
    |
    | Si se activa esta opción, se usará el archivo de canales del proyecto
    | ubicado en routes/channels.php en lugar del predeterminado del paquete.
    |
    */

    'custom_channels' => false,

    'broadcast_channel_type' => env('WHATSAPP_BROADCAST_CHANNEL_TYPE', 'public'), // 'public' o 'private'

    /*
    |--------------------------------------------------------------------------
    | Migraciones Automáticas
    |--------------------------------------------------------------------------
    |
    | Controla si las migraciones del paquete deben cargarse automáticamente.
    | Si no deseas que las migraciones se carguen automáticamente, puedes
    | establecer este valor en "false".
    |
    */
    'load_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Mark messages as read in WhatsApp API
    |--------------------------------------------------------------------------
    |
    | When true, messages will be marked as read in the WhatsApp API when marked
    | as read in the database.
    |
    */
    'mark_read_in_api' => env('WHATSAPP_MARK_READ_IN_API', true),

    /*
    |--------------------------------------------------------------------------
    | Configuración del bots
    |--------------------------------------------------------------------------
    |
    | Configuración para habilitar bots del packete whatsapp-bot
    | https://github.com/djdang3r/whatsapp-bot
    |
    */
    'whatsapp_bot' => [
        // bot_anable: Habilita o deshabilita el bot
        'bot_enable' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Meta OAuth
    |--------------------------------------------------------------------------
    |
    | Credenciales y parámetros para la autenticación con Meta/Facebook.
    |
    */
    'meta_auth' => [
        'client_id'     => env('META_CLIENT_ID'),
        'client_secret' => env('META_CLIENT_SECRET'),
        'redirect_uri'  => env('META_REDIRECT_URI'),
        'scopes'        => env('META_SCOPES', 'whatsapp_business_management,whatsapp_business_messaging'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Códigos de país personalizados
    |---------------------------------------------------------------------------
    |
    | Agrega aquí los códigos de país personalizados si es necesario.
    | Sobreescribirá los códigos predeterminados.
    |
    */
    'custom_country_codes' => [
        // Agrega aquí los códigos de país personalizados si es necesario
        // Ejemplo: '57' => 'CO',
    ],

    /*
    |---------------------------------------------------------------------------
    | Tareas CRON
    |---------------------------------------------------------------------------
    |
    | Configuración de las tareas programadas (CRON) para la recolección de datos
    | y otras tareas automatizadas.
    | Para usar recuerda agrega lo siguiente a tu archivo routes/console.php:
    |
    | use Illuminate\Support\Facades\Schedule;
    |
    | if (config('whatsapp.crontimes.get_general_template_analytics.enabled', false)) {
    |     Schedule::command('whatsapp:get-general-template-analytics')
    |         //->onOneServer()
    |         //->runInBackground()
    |         ->withoutOverlapping(60)
    |         ->cron(config('whatsapp.crontimes.get_general_template_analytics.schedule', '0 0 * * *'))
    |         ->onSuccess(function () {
    |             Log::info('WhatsApp Template Analytics: Tarea completada exitosamente');
    |         })
    |         ->onFailure(function () {
    |             Log::error('WhatsApp Template Analytics: Tarea falló');
    |         });
    | }
    |
    | Y asegúrate de tener configurado el CRON en tu servidor:
    | * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
    |
    */
    'crontimes' => [
        //Patrón de tiempo para tarea CRON que obtiene las estadísticas GENERALES de plantillas
        'get_general_template_analytics' => [
            'enabled'=> env('WHATSAPP_CRON_GET_GENERAL_TEMPLATE_ANALYTICS', false), //Activar o desactivar la tarea CRON
            'schedule' => env('WHATSAPP_CRONTIME_GET_GENERAL_TEMPLATE_ANALYTICS', '0 0 * * *'), //Diario a la medianoche por default
        ],
    ],

    /**
     * Configuración para el proceso de registro embebido de WhatsApp Business.
     *
     * - 'flow_type': Define el tipo de flujo para el registro embebido. Puede ser 'standard' o 'coexistence'.
     *   El valor predeterminado es 'standard'. Se puede configurar mediante la variable de entorno WHATSAPP_EMBEDDED_SIGNUP_FLOW.
     *
     * - 'config_id': Identificador de configuración de Meta necesario para el registro embebido.
     *   Se debe establecer mediante la variable de entorno WHATSAPP_EMBEDDED_CONFIG_ID.
     *
     * - 'app_secret': Secreto de la aplicación de Meta necesario para el registro embebido.
     *   Se debe establecer mediante la variable de entorno WHATSAPP_EMBEDDED_APP_SECRET.
     */
    'embedded_signup' => [
        'flow_type' => env('WHATSAPP_EMBEDDED_SIGNUP_FLOW', 'standard'), // 'standard' o 'coexistence'
        'config_id' => env('WHATSAPP_EMBEDDED_CONFIG_ID'), // Tu Configuration ID de Meta que es el identificador de la app de whatsapp
        'app_secret' => env('WHATSAPP_EMBEDDED_APP_SECRET'), // Tu App Secret de Meta para el registro embebido
    ],
];