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
   WHATSAPP_API_URL=https://graph.facebook.com
   WHATSAPP_API_VERSION=v21.0
   WHATSAPP_VERIFY_TOKEN=your-verify-token
   WHATSAPP_USER_MODEL=App\Models\User


🔄 Personalizar el Modelo User

Si usas un modelo User personalizado:

   Si estás utilizando un modelo User personalizado, asegúrate de especificarlo en tu archivo `.env`:

   ```env
   WHATSAPP_USER_MODEL=App\Models\YourCustomUserModel
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


Este comando publicará las migraciones del paquete en tu directorio `database/migrations`. Puedes personalizarlas según tus necesidades antes de ejecutarlas.

📡 Configuración de Webhooks en Meta
Ir a Meta Developers

Configurar Webhook:
- Define la URL del webhook en la consola de Meta Developers.
- La URL debe apuntar a la ruta publicada por el paquete, por ejemplo

URL: https://tudominio.com/whatsapp-webhook

Token: EL_TOKEN_DE_TU_.ENV

Eventos a suscribir: messages, message_statuses

Tambien puedes usar la herramienta nrock
🧩 Estructura del Paquete

```bash
whatsapp-manager/
├── .env.testing              # Archivo de configuración para pruebas
├── composer.json             # Configuración de dependencias del paquete
├── composer.lock             # Archivo de bloqueo de dependencias
├── LICENSE                   # Licencia del paquete
├── phpunit.xml               # Configuración de PHPUnit para pruebas
├── README.md                 # Documentación principal del paquete
├── .vscode/
│   └── settings.json         # Configuración específica para Visual Studio Code
├── assets/                   # Archivos de recursos
│   ├── 2394384167581644.ogg  # Archivo de audio de ejemplo
│   ├── LARAVEL WHATSAPP MANEGER.pdf # Documento PDF de ejemplo
│   └── laravel-whatsapp-manager.png # Imagen de ejemplo
├── src/                      # Código fuente principal del paquete
│   ├── Config/               # Archivos de configuración
│   ├── Console/              # Comandos Artisan personalizados
│   ├── Database/             # Migraciones y seeders
│   │   ├── Migrations/       # Migraciones de base de datos
│   │   └── Seeders/          # Seeders opcionales
│   ├── Enums/                # Enumeraciones del sistema
│   ├── Exceptions/           # Excepciones personalizadas
│   ├── Facades/              # Facades del paquete
│   ├── Helpers/              # Funciones y utilidades auxiliares
│   ├── Http/                 # Lógica HTTP
│   │   ├── Controllers/      # Controladores HTTP y Webhook
│   │   └── Middleware/       # Middleware personalizados
│   ├── Logging/              # Personalización de logs
│   ├── Models/               # Modelos Eloquent
│   ├── Providers/            # Proveedores de servicios del paquete
│   ├── Repositories/         # Repositorios para acceso a datos
│   ├── routes/               # Rutas del paquete
│   ├── Services/             # Lógica de negocio y API
│   ├── Traits/               # Traits reutilizables
│   └── WhatsappApi/          # Cliente API y endpoints
├── tests/                    # Pruebas del paquete
│   ├── TestCase.php          # Clase base para pruebas
│   ├── Feature/              # Pruebas funcionales
│   └── Unit/                 # Pruebas unitarias
└── vendor/                   # Dependencias instaladas por Composer
```


📖 Guía de Usuario

1. Registro de Cuentas de Negocios
Registra una cuenta de negocios en WhatsApp Business API.
Se hace la peticion a la API de whatsapp, se obtienen los datos de la cuenta y se almacenan en la base de datos. Este metodo obtiene los datos de la cuenta, los telefonos de whatsapp asociados a la cuenta y el perfil de cada numero de telefono.
- Se usa para Obtener los datos desde la API y alojarlos en la base de datos.

```bash
<?php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$account = Whatsapp::account()->register([
   'api_token' => '***********************',
   'business_id' => '1243432234423'
]);
```


2. Obtener Detalles de Números de Teléfono
Obtén información detallada sobre un número de teléfono registrado.
Se hace la peticion a la API de whatsapp para obtener detalles del numero de whatsapp y se almacenan en la base de datos, si el numero ya existe actualiza la informacion.

```bash
<?php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$phoneDetails = Whatsapp::phone()->getPhoneNumberDetails('564565346546');
```


3. Obtener Cuentas de Negocios
Obtén información sobre una cuenta de negocios específica.
Se hace la peticion a la API de whatsapp para obtener informacion sobre una cuenta en especifico, se almacenan los datos en la base de datos.

```bash
<?php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$account = Whatsapp::phone()->getBusinessAccount('356456456456');
```


4. Enviar Mensajes de Texto
Envía mensajes de texto simples.

```bash
<?php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$message = Whatsapp::message()->sendTextMessage(
    '01JTKF55PCNNWTNEKCGMJAZV93', // ID del número de teléfono
    '57',                        // Código de país
    '3237121901',                // Número de teléfono
    'Hola, este es un mensaje de prueba.' // Contenido del mensaje
);
```


Enviar Mensajes de Texto con Enlaces
Envía mensajes de texto simples.

```bash
<?php
$message = Whatsapp::message()->sendTextMessage(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    'Visítanos en YouTube: http://youtube.com',
    true // Habilitar vista previa de enlaces
);
```


5. Enviar Respuestas a Mensajes
Responde a un mensaje existente.

```bash
<?php
$message = Whatsapp::message()->sendReplyTextMessage(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    'wamid.HBgMNTczMTM3MTgxOTA4FQIAEhggNzVCNUQzRDMxRjhEMUJEM0JERjAzNkZCNDk5RDcyQjQA', // ID del mensaje de contexto
    'Esta es una respuesta al mensaje anterior.'
);
```



6. Reacciones a Mensajes
Envía una reacción a un mensaje existente.

```bash
<?php
$message = Whatsapp::message()->sendReplyReactionMessage(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    'wamid.HBgMNTczMTM3MTgxOTA4FQIAEhggNzZENDMzMEI0MDRFQzg0OUUwRTI1M0JBQjEzMUZFRUYA', // ID del mensaje de contexto
    '😂' // Emoji de reacción
);
```



7. Enviar Mensajes Multimedia
Enviar Imágenes

```bash
<?php
$filePath = storage_path('app/public/laravel-whatsapp-manager.png');
$file = new \SplFileInfo($filePath);

$message = Whatsapp::message()->sendImageMessage(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    $file
);
```

Enviar Imágenes por URL

```bash
<?php
$message = Whatsapp::message()->sendImageMessageByUrl(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    'https://example.com/image.png'
);
```

Enviar Audio

```bash
<?php
$filePath = storage_path('app/public/audio.ogg');
$file = new \SplFileInfo($filePath);

$message = Whatsapp::message()->sendAudioMessage(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    $file
);
```

Enviar Audio por URL

```bash
<?php
$message = Whatsapp::message()->sendAudioMessageByUrl(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    'https://example.com/audio.ogg'
);
```

Enviar Documentos

```bash
<?php
$filePath = storage_path('app/public/document.pdf');
$file = new \SplFileInfo($filePath);

$message = Whatsapp::message()->sendDocumentMessage(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    $file
);
```

Enviar Documentos por URL

```bash
<?php
$message = Whatsapp::message()->sendDocumentMessageByUrl(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    'https://example.com/document.pdf'
);
```

8. Enviar Mensajes de Ubicación
Envía un mensaje con coordenadas de ubicación.

```bash
<?php
$message = Whatsapp::message()->sendLocationMessage(
    '01JTKF55PCNNWTNEKCGMJAZV93',
    '57',
    '3237121901',
    4.7110, // Latitud
    -74.0721, // Longitud
    'Bogotá', // Nombre del lugar
    'Colombia' // Dirección
);
```


9. Obtener todas las plantillas de una cuenta de whatsapp
Se obtienen todas las plantillas de una cuenta de whatsapp y se almacenan en la base de datos.
Se hace la peticion a la API de whatsapp para obtener todas las plantillas que estan asociadas a la cuenta de whatsapp.

```bash
<?php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Obtener una instancia de WhatsApp Business Account
$account = WhatsappBusinessAccount::find($accountId);

// Obtener todas las plantillas de la cuenta
Whatsapp::template()->getTemplates($account);
```

- Obtener una plantilla por el nombre.
  Se hace la peticion a la API de whatsapp para obtener una plantilla por el nombre y se almacena en la base de datos.

   ```bash
   <?php
   use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

   // Obtener una instancia de WhatsApp Business Account
   $account = WhatsappBusinessAccount::find($accountId);

   // Obtener plantilla por su nombre
   $template = Whatsapp::template()->getTemplateByName($account, 'order_confirmation');
   ```


- Obtener una plantilla por el ID.
  Se hace la peticion a la API de whatsapp para obtener una plantilla por el ID y se almacena en la base de datos.

   ```bash
   <?php
   use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

   // Obtener una instancia de WhatsApp Business Account
   $account = WhatsappBusinessAccount::find($accountId);

   // Obtener plantilla por su ID
   $template = Whatsapp::template()->getTemplateById($account, '559947779843204');
   ```

- Eliminar plantilla de la API y de la base de datos al mismo tiempo.
  Se hace la peticion a la API de whatsapp para obtener una plantilla por el ID y se almacena en la base de datos.

   ```bash
   <?php
   use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

   // Obtener una instancia de WhatsApp Business Account
   $account = WhatsappBusinessAccount::find($accountId);

   // Soft delete
   // Eliminar plantilla por su ID
   $template = Whatsapp::template()->gdeleteTemplateById($account, $templateId);

   // Eliminar plantilla por su Nombre
   $template = Whatsapp::template()->deleteTemplateByName($account, 'order_confirmation');


   // Hard delete
   // Eliminar plantilla por su ID
   $template = Whatsapp::template()->gdeleteTemplateById($account, $templateId, true);

   // Eliminar plantilla por su Nombre
   $template = Whatsapp::template()->deleteTemplateByName($account, 'order_confirmation', true);
   ```


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
