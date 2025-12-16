<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Package Language / Idioma del Paquete
    |--------------------------------------------------------------------------
    |
    | This setting controls the language for package messages, logs, and console
    | output. You can set it to 'en' (English) or 'es' (Spanish).
    | By default, it uses the application's locale.
    |
    | Esta configuraciÃ³n controla el idioma para mensajes del paquete, logs y
    | salida de consola. Puedes configurarlo como 'en' (InglÃ©s) o 'es' (EspaÃ±ol).
    | Por defecto, usa el idioma de la aplicaciÃ³n.
    |
    */
    'locale' => env('WHATSAPP_LOCALE', config('app.locale', 'en')),


    /*
    |--------------------------------------------------------------------------
    | Custom Models / Modelos Personalizados
    |--------------------------------------------------------------------------
    |
    | Here you can specify the models that the package will use for the
    | main entities. You can override these values in your .env file
    | if you are using custom models.
    |
    | Aquí puedes especificar los modelos que el paquete utilizará para las
    | entidades principales. Puedes sobrescribir estos valores en tu archivo
    | .env si estás utilizando modelos personalizados.
    |
    */
    'models' => [
        // Contacts / Contactos
        'contact' => \ScriptDevelop\WhatsappManager\Models\Contact::class,

        'conversation' => \ScriptDevelop\WhatsappManager\Models\Conversation::class,

        // Multimedia files / Archivos multimedia
        'media_file' => \ScriptDevelop\WhatsappManager\Models\MediaFile::class,

        // Messages / Mensajes
        'message' => \ScriptDevelop\WhatsappManager\Models\Message::class,

        // Templates / Plantillas
        'template' => \ScriptDevelop\WhatsappManager\Models\Template::class,

        // Template categories / Categorías de Plantillas
        'template_category' => \ScriptDevelop\WhatsappManager\Models\TemplateCategory::class,

        // Template components / Componentes de Plantillas
        'template_component' => \ScriptDevelop\WhatsappManager\Models\TemplateComponent::class,

        // Template languages / Idiomas de Plantillas
        'template_language' => \ScriptDevelop\WhatsappManager\Models\TemplateLanguage::class,

        // Template versions / Versiones de plantillas
        'template_version' => \ScriptDevelop\WhatsappManager\Models\TemplateVersion::class,

        // Template analytics / Analíticas de plantillas
        'general_template_analytics' => \ScriptDevelop\WhatsappManager\Models\GeneralTemplateAnalytics::class,

        // Template analytics clicked / Analíticas de clics en plantillas
        'general_template_analytics_clicked' => \ScriptDevelop\WhatsappManager\Models\GeneralTemplateAnalyticsClicked::class,

        // Template analytics cost / Costos de analíticas de plantillas
        'general_template_analytics_cost' => \ScriptDevelop\WhatsappManager\Models\GeneralTemplateAnalyticsCost::class,

        'website' => \ScriptDevelop\WhatsappManager\Models\Website::class,

        // WhatsApp Business Account model / Modelo para la cuenta empresarial de WhatsApp
        'business_account' => \ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount::class,

        // WhatsApp account profile / Perfil de la cuenta de Whatsapp
        'business_profile' => \ScriptDevelop\WhatsappManager\Models\WhatsappBusinessProfile::class,

        'flow' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlow::class,

        'flow_event' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlowEvent::class,

        'flow_response' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlowResponse::class,

        'flow_screen' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlowScreen::class,

        'flow_session' => \ScriptDevelop\WhatsappManager\Models\WhatsappFlowSession::class,

        // Phone numbers configured in WhatsApp Business / Números de celular configurados en Whatsapp Business
        'phone_number' => \ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber::class,

        'screen_element' => \ScriptDevelop\WhatsappManager\Models\WhatsappScreenElement::class,

        'template_flow' => \ScriptDevelop\WhatsappManager\Models\WhatsappTemplateFlow::class,

        'blocked_user' => \ScriptDevelop\WhatsappManager\Models\BlockedUser::class,

        // User model (can be customized) / Modelo de usuario (puede ser personalizado)
        'user_model' => env('AUTH_MODEL', 'App\Models\User::class'),

        // User table (can be customized) / Tabla de usuarios (puede ser personalizada)
        //'user_table' => env('AUTH_TABLE', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Events / Eventos Personalizados
    |--------------------------------------------------------------------------
    |
    | Here you can specify the events that the package will use.
    | You can change these classes to custom ones.
    |
    | Aquí puedes especificar los eventos que el paquete utilizará.
    | Puedes cambiar estas clases por personalizadas.
    |
    */
    'events' => [
        // This event fires when account is updated / Este evento se dispara cuando se actualiza
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
    | WhatsApp API Configuration / Configuración de la API de WhatsApp
    |--------------------------------------------------------------------------
    |
    | Main configuration to interact with the WhatsApp Business API.
    | Includes the base URL, API version, timeout, and retry options
    | in case of errors.
    |
    | Configuración principal para interactuar con la API de WhatsApp Business.
    | Incluye la URL base, la versión de la API, el tiempo de espera y las
    | opciones de reintento en caso de errores.
    |
    */
    'api' => [
        // WhatsApp API base URL / URL base de la API de WhatsApp
        'base_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'),

        // WhatsApp API version / Versión de la API de WhatsApp
        // Warning: Be careful when changing the version, it may affect functionalities and generate errors if future versions change the endpoint structure
        // Advertencia: Cuidado al cambiar la versión, puede afectar a las funcionalidades y generar errores si versiones futuras cambian la estructura de los endpoints
        // Make sure the version is compatible with your current implementation / Asegúrate de que la versión sea compatible con tu implementación actual.
        'version' => env('WHATSAPP_API_VERSION', 'v22.0'),

        // Request timeout (in seconds) / Tiempo de espera para las solicitudes (en segundos)
        'timeout' => env('WHATSAPP_API_TIMEOUT', 30),

        // Retry configuration in case of errors / Configuración de reintentos en caso de errores
        'retry' => [
            'attempts' => 3, // Number of attempts / Número de intentos
            'delay' => 500, // Wait time between attempts (in milliseconds) / Tiempo de espera entre intentos (en milisegundos)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration / Configuración del Webhook
    |--------------------------------------------------------------------------
    |
    | Configuration for the WhatsApp webhook. Includes the verification token
    | used to validate incoming requests from Meta.
    |
    | Configuración para el webhook de WhatsApp. Incluye el token de verificación
    | que se utiliza para validar las solicitudes entrantes desde Meta.
    |
    */
    'webhook' => [
        // Webhook verification token / Token de verificación para el webhook
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

        // Custom processor for webhooks (default value) / Procesador personalizado para webhooks (valor por defecto)
        'processor' => \ScriptDevelop\WhatsappManager\Services\WebhookProcessors\BaseWebhookProcessor::class,

        // Default subscribed fields for webhooks (alphabetically ordered) / Campos suscritos por defecto para los webhooks (ordenados alfabéticamente)
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
    | Media Configuration / Configuración de Medios
    |--------------------------------------------------------------------------
    |
    | Configuration for multimedia file management. Includes maximum allowed
    | sizes and accepted MIME types for each file type.
    |
    | Configuración para la gestión de archivos multimedia. Incluye los tamaños
    | máximos permitidos y los tipos MIME aceptados para cada tipo de archivo.
    |
    */
    'media' => [
        // Storage directories for each file type / Directorios de almacenamiento para cada tipo de archivo
        'storage_path' => [
            'images' => storage_path('app/public/whatsapp/images'),
            'audios' => storage_path('app/public/whatsapp/audios'),
            'documents' => storage_path('app/public/whatsapp/documents'),
            'videos' => storage_path('app/public/whatsapp/videos'),
            'stickers' => storage_path('app/public/whatsapp/stickers'),
        ],
        // Maximum allowed size for each file type (in bytes) / Tamaño máximo permitido para cada tipo de archivo (en bytes)
        'max_file_size' => [
            'image' => 5 * 1024 * 1024, // 5MB
            'audio' => 16 * 1024 * 1024, // 16MB
            'video' => 16 * 1024 * 1024, // 16MB
            'document' => 100 * 1024 * 1024, // 100MB
            'sticker' => 100 * 1024, // 100KB
        ],

        // Allowed MIME types for each file type / Tipos MIME permitidos para cada tipo de archivo
        // Warning: Make sure MIME types match those accepted by WhatsApp / Advertencia: Asegúrate de que los tipos MIME coincidan con los que WhatsApp acepta.
        'allowed_types' => [
            'image' => ['image/jpeg', 'image/png'], // Images / Imágenes
            'audio' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'], // Audio / Audios
            'video' => ['video/mp4', 'video/3gp'], // Videos
            'document' => [ // Documents / Documentos
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
    | Channels Customization / Personalización de canales
    |--------------------------------------------------------------------------
    |
    | If this option is enabled, the project's channels file located at
    | routes/channels.php will be used instead of the package's default.
    |
    | Si se activa esta opción, se usará el archivo de canales del proyecto
    | ubicado en routes/channels.php en lugar del predeterminado del paquete.
    |
    */

    'custom_channels' => false,

    'broadcast_channel_type' => env('WHATSAPP_BROADCAST_CHANNEL_TYPE', 'public'), // 'public' or 'private' / 'public' o 'private'

    /*
    |--------------------------------------------------------------------------
    | Automatic Migrations / Migraciones Automáticas
    |--------------------------------------------------------------------------
    |
    | Controls whether package migrations should be loaded automatically.
    | If you don't want migrations to be loaded automatically, you can
    | set this value to "false".
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
    | Bot Configuration / Configuración del bots
    |--------------------------------------------------------------------------
    |
    | Configuration to enable bots from the whatsapp-bot package
    | https://github.com/djdang3r/whatsapp-bot
    |
    | Configuración para habilitar bots del packete whatsapp-bot
    | https://github.com/djdang3r/whatsapp-bot
    |
    */
    'whatsapp_bot' => [
        // bot_enable: Enables or disables the bot / Habilita o deshabilita el bot
        'bot_enable' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta OAuth Configuration / Configuración de Meta OAuth
    |--------------------------------------------------------------------------
    |
    | Credentials and parameters for Meta/Facebook authentication.
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
    | Custom Country Codes / Códigos de país personalizados
    |---------------------------------------------------------------------------
    |
    | Add custom country codes here if necessary.
    | Will override default codes.
    |
    | Agrega aquí los códigos de país personalizados si es necesario.
    | Sobreescribirá los códigos predeterminados.
    |
    */
    'custom_country_codes' => [
        // Add custom country codes here if necessary / Agrega aquí los códigos de país personalizados si es necesario
        // Example / Ejemplo: '57' => 'CO',
    ],

    /*
    |---------------------------------------------------------------------------
    | CRON Tasks / Tareas CRON
    |---------------------------------------------------------------------------
    |
    | Configuration for scheduled tasks (CRON) for data collection
    | and other automated tasks.
    |
    | Configuración de las tareas programadas (CRON) para la recolección de datos
    | y otras tareas automatizadas.
    |
    | To use, remember to add the following to your routes/console.php file:
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
    | And make sure you have CRON configured on your server:
    | Y asegúrate de tener configurado el CRON en tu servidor:
    | * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
    |
    */
    'crontimes' => [
        // Time pattern for CRON task that gets GENERAL template analytics
        // Patrón de tiempo para tarea CRON que obtiene las estadísticas GENERALES de plantillas
        'get_general_template_analytics' => [
            'enabled'=> env('WHATSAPP_CRON_GET_GENERAL_TEMPLATE_ANALYTICS', false), // Enable or disable the CRON task / Activar o desactivar la tarea CRON
            'schedule' => env('WHATSAPP_CRONTIME_GET_GENERAL_TEMPLATE_ANALYTICS', '0 0 * * *'), // Daily at midnight by default / Diario a la medianoche por default
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

