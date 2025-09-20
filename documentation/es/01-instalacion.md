
---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="../../README.md" title="Sección anterior: Inicio">◄◄ Inicio</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
    <td align="right">
      <a href="02-config-api.md" title="Sección siguiente">Configurar API ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentación del Webhook de WhatsApp Manager | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---
## 🚀 Instalación Completa

### 📋 Requisitos Previos
Antes de instalar el paquete, necesitarás una cuenta de WhatsApp API Cloud:

> **📹 Tutoriales recomendados:**
> - [Cómo obtener una cuenta gratis - AdBoostPro](https://www.youtube.com/watch?v=of6dEsKSh-0)
> - [Configuración inicial - Bismarck Aragón](https://www.youtube.com/watch?v=gdD_0ernIqM)

---

### 🔧 Pasos de Instalación

1. **Instalar el paquete vía Composer**:
    ```bash
    composer require scriptdevelop/whatsapp-manager
    ```

2. **Publicar archivos de configuración:**:
    Este comando publicara archivos de configuracion base del paquete:
   - Configuración principal (config/whatsapp.php).
   - Configuración de logs (config/logging.php).
   - Configuración principal del paquete.
        
    ```bash
    php artisan vendor:publish --tag=whatsapp-config
    ```

3. **Configurar logging (config/logging.php):**:
    Añadir el canal whatsapp.
    - En el archivo "config/logging.php", se debe a;adir nuevo canal para los logs dal paquete.
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

4. **Publicar migraciones (opcional):**:
    Este comando publicara las migraciones del paquete no es necesario publicarlas ya que al hacer "php artisan migrate", se tomaran las migraciones directamente desde el paquete. SI deseas puedes publicarlas y editarlas a gusto.

    ```bash
    php artisan vendor:publish --tag=whatsapp-migrations
    ```

5. **Publicar migraciones (opcional):**:
    Este comando publicara el archivos de rutas para el webhook. Es obligatorio ya que se necesita para recibir notificaciones de la mensajeria entrante.

    ```bash
    php artisan vendor:publish --tag=whatsapp-routes
    ```

6. **Excluir webhook de CSRF (bootstrap/app.php):**:
    se debe excluir las rutas del webhook para el CSRF. En el archivo "bootstrap/app.php".

    ```php
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/whatsapp-webhook',
        ]);
    })
    ```

7. **Configurar variables de entorno (.env):**:
    ```sh
    WHATSAPP_API_URL=https://graph.facebook.com
    WHATSAPP_API_VERSION=v21.0
    WHATSAPP_VERIFY_TOKEN=your-verify-token
    WHATSAPP_USER_MODEL=App\Models\User
    WHATSAPP_BROADCAST_CHANNEL_TYPE=private

    META_CLIENT_ID=123456789012345
    META_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    META_REDIRECT_URI=https://tudominio.com/meta/callback
    META_SCOPES=whatsapp_business_management,whatsapp_business_messaging
    ```
---

## **🗃️ Configuración de Base de Datos:**:

1. **Ejecutar migraciones:**
    ```sh
    php artisan migrate
    ```

2. **Publicar y ejecutar seeders de idiomas:**
    ```sh
    php artisan vendor:publish --tag=whatsapp-seeders
    php artisan db:seed --class=WhatsappTemplateLanguageSeeder
    ```

>⚠️ Importante:
>Los seeders son necesarios para trabajar con plantillas de WhatsApp

---

## **📁 Configuración de Archivos Multimedia:**:

1. **Crear estructura de directorios:**
    ```sh
    storage/app/public/whatsapp/
    ├── audios/
    ├── documents/
    ├── images/
    ├── stickers/
    └── videos/
    ```


2. **Publicar estructura automática (opcional):**
    ```sh
    php artisan vendor:publish --tag=whatsapp-media
    ```

3. **Crear enlace simbólico:**
    ```sh
    php artisan storage:link
    ```

---

## **🔗 Configuración de Webhooks en Meta:**:

**Sigue estos pasos para configurar los webhooks en la plataforma de Meta Developers:**

1. Accede a Meta for Developers
2. Selecciona tu aplicación
3. Navega a Productos > WhatsApp > Configuración
4. En la sección Webhooks:
    - URL del Webhook: https://tudominio.com/whatsapp-webhook
    - Token de verificación: Valor de WHATSAPP_VERIFY_TOKEN en tu .env
    - Eventos a suscribir:
        - messages
        - message_statuses
        - message_template_status_update (opcional)

> ⚠️ Importante:
>Para la ruta en local puedes usar la herramienta Nrock que mas abajo decribimos.

**Resumen de configuración:**

| Parámetro         | Valor recomendado                                  |
|-------------------|---------------------------------------------------|
| URL del Webhook   | `https://tudominio.com/whatsapp-webhook`          |
| Token             | El valor de `WHATSAPP_VERIFY_TOKEN` en tu `.env`  |
| Eventos           | `messages`, `message_statuses`                    |





## **🛠️ Nrock - Herramientas para Desarrollo Local:**:
**Usando ngrok para pruebas locales:**
1. Descarga ngrok desde ngrok.com
2. Ejecuta tu servidor local:
    ```sh
    php artisan serve
    ```
3. Expón tu servidor local:
    ```sh
    ngrok http http://localhost:8000
    
    ngrok http --host-header=rewrite 8000
    ```
4. Usa la URL generada por ngrok como tu webhook en Meta:
    ```sh
    https://xxxxxx.ngrok.io/whatsapp-webhook
    ```


## 🔍 Validación Final
**Después de completar la instalación, verifica:**

1. Las rutas están publicadas y accesibles.
2. El token de verificación coincide en .env y Meta.
3. Los directorios multimedia tienen permisos de escritura.
4. El enlace simbólico de storage funciona correctamente.
5. Los eventos seleccionados en Meta cubren tus necesidades.

>💡 Consejo:
>Para probar la configuración, envía un mensaje de prueba a tu número de WhatsApp Business y verifica que aparece en los logs (storage/logs/whatsapp.log).



<br>

---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="../../README.md" title="Sección anterior: Inicio">◄◄ Inicio</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
    <td align="right">
      <a href="02-config-api.md" title="Sección siguiente">Configurar API ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentación del Webhook de WhatsApp Manager | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---



## ❤️ Apoyo

Si este proyecto te resulta útil, considera apoyar su desarrollo:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donar%20con-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

## 📄 Licencia

MIT License - Ver [LICENSE](LICENSE) para más detalles





