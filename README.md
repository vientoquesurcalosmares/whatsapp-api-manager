# 📱 WhatsApp Business API Manager for Laravel

LARAVEL WHatsapp Manager

**Un paquete elegante y potente para integrar WhatsApp Business API en tus aplicaciones Laravel 12+.**  
✨ Gestión de mensajes, plantillas, campañas, flujos conversacionales, métricas y más.

---

## 🚀 Características Principales

- **Envía mensajes** de texto, multimedia, interactivos.
- **Webhooks integrados** para recibir mensajes y actualizaciones.
- **Gestión de conversaciones** con métricas de cobro. 💰
- **Bots conversacionales** con flujos dinámicos. 🤖
- **Sincronización automática** de números telefónicos y perfiles.
- **Soporte para campañas** masivas programadas. 📅
- 100% compatible con **Laravel Echo** para notificaciones en tiempo real.

---

---

## 🚀 Instalación

1. **Instala el paquete vía Composer**:
   ```bash
   composer require scriptdevelop/whatsapp-manager
   ```

2. **Publica la configuración (opcional)**:
   ```bash
   php artisan vendor:publish --tag=whatsapp-config
   ```

   ⚙️ Configuración

   Configuración principal (config/whatsapp.php):
      
      ```php
      return [

         'api' => [
            'base_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'),
            'version' => env('WHATSAPP_API_VERSION', 'v19.0'),
            'timeout' => env('WHATSAPP_API_TIMEOUT', 30),
            'retry' => [
                  'attempts' => 3,
                  'delay' => 500,
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
      ```
   Configuración de logs (config/logging.php):

   Configuración principal del paquete:
   Añadir el canal whatsapp.

      ```php
      'channels' => [
         'whatsapp' => [
               'driver' => 'daily',
               'path' => storage_path('logs/whatsapp.log'),
               'level' => 'debug',
               'days' => 7,
               'tap' => [\ScriptDevelop\WhatsappManager\Logging\CustomizeFormatter::class],
         ],
      ],
      ```

3. **Publica las migraciones (opcional)**:
   ```bash
   php artisan vendor:publish --tag=whatsapp-migrations

4. **Publica las rutas (OBLIGATORIO)**:
   Se necesita para el webhook.

   ```bash
   php artisan vendor:publish --tag=whatsapp-routes
   ```

   Excluir rutas del webhook de CSRF:

   Al publicar las rutas es importante anexar las rutas del webhook a las excepciones del CSRF.
   En bootstrap/app.php:

   ```php
   ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/whatsapp-webhook',
        ]);
    })
   ```

5. **Configura tus credenciales en .env**:
   ```bash
   WHATSAPP_USER_MODEL=\App\Models\User::class
   WHATSAPP_API_URL='https://graph.facebook.com/'
   WHATSAPP_API_VERSION="v19.0"
   WHATSAPP_SYNC_ON_QUERY=true


🔄 Personalizar el Modelo User

Si usas un modelo User personalizado:

   Si estás utilizando un modelo User personalizado, asegúrate de especificarlo en tu archivo `.env`:

   ```env
   WHATSAPP_USER_MODEL=App\Modules\Auth\Models\Admin
   ```

Además, verifica que el modelo implementa las interfaces necesarias o extiende el modelo base esperado por el paquete. Por ejemplo:

```php
namespace App\Modules\Auth\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
   // Tu lógica personalizada aquí
}
```


6.  🗃️ Migraciones

🔍 Verificar configuración del User Model

**Verifica el modelo de usuario configurado**:

Ejecuta el siguiente comando para asegurarte de que el modelo de usuario está correctamente configurado:

```bash
php artisan whatsapp:check-user-model
```

Este comando validará que el modelo especificado en el archivo `.env` cumple con los requisitos del paquete.

Salida esperada (ejemplo):
```plaintext
✅ Modelo User configurado: App\Models\User
```

Si hay algún problema, revisa la configuración en tu archivo `.env` y asegúrate de que el modelo implementa las interfaces necesarias.


Ejecuta las migraciones para crear las tablas necesarias:
   
```bash
php artisan migrate
```

Esto ejecutará las migraciones necesarias para crear las tablas requeridas por el paquete en tu base de datos.

Tablas incluidas:

- whatsapp_business_accounts 📇  
- whatsapp_phone_numbers ☎️  
- campaigns 📢  
- chat_sessions 💬  
- message_templates 📝  
- messages 📩  
- message_logs 📜  
- contacts 📋  
- contact_groups 👥  
- group_contacts 🔗  
- scheduled_messages ⏰  
- message_attachments 📎  
- api_tokens 🔑  
- webhook_events 🌐  
- conversation_flows 🔄  
- flow_steps 🛠️  
- flow_conditions ⚙️  


📦 Publicar elementos adicionales (opcional)

```bash
php artisan vendor:publish --tag=whatsapp-migrations  # Publicar migraciones
```

Este comando publicará las migraciones del paquete en tu directorio `database/migrations`. Puedes personalizarlas según tus necesidades antes de ejecutarlas.

📡 Configuración de Webhooks en Meta
Ir a Meta Developers

Configurar Webhook:

URL: https://tudominio.com/whatsapp-webhook

Token: EL_TOKEN_DE_TU_.ENV

Eventos a suscribir: messages, message_statuses

Tambien puedes usar la herramienta nrock
🧩 Estructura del Paquete

whatsapp-manager/
├── src/
│   ├── Models/               # Modelos Eloquent
│   ├── Services/             # Lógica de negocio y API
│   ├── Console/              # Comandos Artisan personalizados
│   ├── Database/
│   │   ├── Migrations/       # Migraciones de base de datos
│   │   └── Seeders/          # Seeders opcionales
│   ├── Http/
│   │   ├── Controllers/      # Controladores HTTP y Webhook
│   │   └── Middleware/       # Middleware personalizados
│   ├── Events/               # Eventos del sistema
│   ├── Listeners/            # Listeners para eventos
│   ├── Notifications/        # Notificaciones y canales
│   ├── Logging/              # Personalización de logs
│   └── Support/              # Utilidades y helpers
├── routes/
│   └── whatsapp.php          # Rutas del paquete (webhook, API)
├── config/
│   └── whatsapp.php          # Configuración principal
└── resources/
   └── views/                # Vistas opcionales para panel o notificaciones

🤝 Contribuir
¡Tu ayuda es bienvenida! Sigue estos pasos:

Haz un fork del repositorio

Crea una rama: git checkout -b feature/nueva-funcionalidad

Haz commit: git commit -m 'Add some feature'

Push: git push origin feature/nueva-funcionalidad

Abre un Pull Request

📄 Licencia
MIT License. Ver LICENSE para más detalles.

👨💻 Soporte
¿Problemas o sugerencias?
📧 Contacto: soporte@scriptdevelop.com
🐞 Reporta un issue: GitHub Issues

Desarrollado con ❤️ por ScriptDevelop
✨ Potenciando tu conexión con WhatsApp Business API


---

### 🔥 Características Destacadas del README
1. **Jerarquía Visual Clara**: Uso de emojis y encabezados para guiar la lectura.
2. **Sintaxis Resaltada**: Bloques de código con syntax highlighting.
3. **Badges Interactivos** (Añade estos al inicio):

   [![Latest Version](https://img.shields.io/packagist/v/scriptdevelop/whatsapp-manager.svg?style=flat-square)](https://packagist.org/packages/scriptdevelop/whatsapp-manager)
   [![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-8892BF.svg?style=flat-square)](https://php.net/)
   [![Laravel Version](https://img.shields.io/badge/Laravel-10%2B-FF2D20.svg?style=flat-square)](https://laravel.com)

4.  Secciones Colapsables (Usa detalles HTML si necesitas):
    <details>
    <summary>📦 Ver estructura completa del paquete</summary>
    <!-- Contenido -->
    </details>
