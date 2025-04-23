# 📱 WhatsApp Business API Manager for Laravel

**Un paquete elegante y potente para integrar WhatsApp Business API en tus aplicaciones Laravel.**  
✨ Gestión de mensajes, plantillas, campañas, flujos conversacionales y más.

---

## 🚀 Instalación

1. **Instala el paquete vía Composer**:
   ```bash
   composer require scriptdevelop/whatsapp-manager


2. **Publica la configuración (opcional)**:
   php artisan vendor:publish --tag=whatsapp-config

3. **Configura tus credenciales en .env**:

    WHATSAPP_USER_MODEL=\App\Models\User::class
    WHATSAPP_API_URL='https://graph.facebook.com/'
    WHATSAPP_API_VERSION="v19.0"

⚙️ Configuración
📁 Archivo config/whatsapp.php

Configuración principal del paquete:

'user_model' => env('WHATSAPP_USER_MODEL', \App\Models\User::class), // Modelo User
'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/'), // Base URL de la API
'api_version' => env('WHATSAPWHATSAPP_API_VERSIONP_USER_MODEL', 'v19.0'), // API Version

🔄 Personalizar el Modelo User
Si usas un modelo User personalizado:

WHATSAPP_USER_MODEL=App\Modules\Auth\Models\Admin

4.  🗃️ Migraciones

🔍 Verificar configuración del User Model

php artisan whatsapp:check-user-model

Salida:
✅ Modelo User configurado: App\Models\User

Ejecuta las migraciones para crear las tablas necesarias:
   
php artisan migrate

Tablas incluidas:

- whatsapp_business_accounts 📇
- whatsapp_phone_numbers ☎️
- campaigns 📢
- chat_sessions 💬
- [+15 tablas relacionadas] 📊

📦 Publicar elementos adicionales (opcional)

php artisan vendor:publish --tag=whatsapp-migrations  # Publicar migraciones

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

   ```markdown
   [![Latest Version](https://img.shields.io/packagist/v/scriptdevelop/whatsapp-manager.svg?style=flat-square)](https://packagist.org/packages/scriptdevelop/whatsapp-manager)
   [![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-8892BF.svg?style=flat-square)](https://php.net/)
   [![Laravel Version](https://img.shields.io/badge/Laravel-10%2B-FF2D20.svg?style=flat-square)](https://laravel.com)

4.  Secciones Colapsables (Usa detalles HTML si necesitas):
    <details>
    <summary>📦 Ver estructura completa del paquete</summary>
    <!-- Contenido -->
    </details>
