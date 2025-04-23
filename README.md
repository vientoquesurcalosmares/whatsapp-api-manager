# 📱 WhatsApp Business API Manager for Laravel

**Un paquete elegante y potente para integrar WhatsApp Business API en tus aplicaciones Laravel.**  
✨ Gestión de mensajes, plantillas, campañas, flujos conversacionales y más.

---

## 🚀 Instalación

1. **Instala el paquete vía Composer**:
   ```bash
   composer require scriptdevelop/whatsapp-manager


2. **Publica la configuración (opcional)**:
   ```bash
   php artisan vendor:publish --tag=whatsapp-config

3. **Configura tus credenciales en .env**:
   ```bash
   WHATSAPP_USER_MODEL=\App\Models\User::class
   WHATSAPP_API_URL='https://graph.facebook.com/'
   WHATSAPP_API_VERSION="v19.0"

⚙️ Configuración
📁 Archivo config/whatsapp.php

Configuración principal del paquete:
   
   ```php
   return [
      'user_model' => env('WHATSAPP_USER_MODEL', \App\Models\User::class), // Modelo User
      'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/'), // Base URL de la API
      'api_version' => env('WHATSAPP_API_VERSION', 'v19.0'), // Versión de la API
   ];
   ```

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


4.  🗃️ Migraciones

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


🧩 Estructura del Paquete

whatsapp-manager/
├── src/
│   ├── Models/           # Modelos Eloquent
│   ├── Services/         # Lógica de negocio
│   ├── Console/          # Comandos Artisan
│   └── Database/         # Migraciones
└── config/               # Configuración

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
