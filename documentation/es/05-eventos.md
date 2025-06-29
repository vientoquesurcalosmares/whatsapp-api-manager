
---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="04-plantillas.md" title="Sección anterior">◄◄ Plantillas</a>
    </td>
    <td align="center">
      <a href="../intro.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
    <td align="right">
      <a href="06-webhook.md" title="Sección siguiente">Webhook ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentación del Webhook de WhatsApp Manager | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---

## 📡 Eventos en Tiempo Real

### Introducción
El sistema de eventos en tiempo real permite a tu aplicación reaccionar instantáneamente a las interacciones de WhatsApp mediante WebSockets. Con la integración de Laravel Reverb y Laravel Echo, puedes recibir notificaciones instantáneas sobre mensajes entrantes, actualizaciones de estado, eventos de plantillas y más, creando experiencias de usuario altamente interactivas y receptivas.

**Beneficios clave:**
- Notificaciones instantáneas sin necesidad de polling
- Actualizaciones de UI en tiempo real
- Menor latencia para una mejor experiencia de usuario
- Integración perfecta con el ecosistema Laravel
- Soporte para canales públicos y privados

### 📚 Tabla de Contenidos

1. Configuración de Laravel Reverb
    - Instalación
    - Configuración del servidor
    - Variables de entorno

2. Configuración de Laravel Echo
    - Instalación de dependencias
    - Configuración frontend
    - Variables de entorno para Vite

3. Eventos Soportados
    - Mensajes
    - Estados
    - Plantillas
    - Interacciones

4. Escuchando Eventos
    - Configuración de canales
    - Ejemplos frontend
    - Pruebas con Tinker

5. Mejores Prácticas
    - Seguridad en canales
    - Manejo de errores
    - Optimización de rendimiento




# 📦 Instalación de Laravel Reverb
### 1. Instala Laravel Reverb vía Composer
En una nueva terminal, ejecuta el siguiente comando:
```php
composer require laravel/reverb
```

### 2. Publica los archivos de configuración de Reverb

```php
php artisan reverb:install
```
Esto generará el archivo config/reverb.php y ajustará tu broadcasting.php para incluir el driver reverb.


### 3. Configura tu archivo .env
Agrega o ajusta las siguientes variables:
```bash
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=whatsapp-app
REVERB_APP_KEY=whatsapp-key
REVERB_APP_SECRET=whatsapp-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
```
⚠️ Estos valores deben coincidir con los definidos en config/reverb.php.


## 4. Configura config/broadcasting.php
Asegúrate de que el driver predeterminado sea reverb:
```php
'default' => env('BROADCAST_CONNECTION', 'null'),
```

Y dentro del array connections, asegúrate de tener esto:
```php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST'),
        'port' => env('REVERB_PORT', 443),
        'scheme' => env('REVERB_SCHEME', 'https'),
        'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
    ],
    'client_options' => [
        // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
    ],
],
```

### 🚀 Levantar el servidor Reverb
En una nueva terminal, ejecuta el siguiente comando:
```php
php artisan reverb:start
```

Deberías ver algo como:
```php
Reverb server started on 127.0.0.1:8080
```

El servidor WebSocket quedará activo en 127.0.0.1:8080.



# 🌐 Configurar Laravel Echo (Frontend)
### 1. Instala las dependencias de frontend:
Instalar Laravel Echo y PusherJS
```bash
npm install --save laravel-echo pusher-js
```

### 2. Configura Echo en resources/js/bootstrap.js o donde inicialices tu JS:

```js
import Echo from 'laravel-echo';

window.Pusher = require('pusher-js');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: false,
    enabledTransports: ['ws'],
});
```

### 3. Asegúrate de tener las variables necesarias en tu .env frontend (Vite):

```bash
VITE_REVERB_APP_KEY=whatsapp-key
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
```


### 📡 Escuchar eventos (ejemplo en JS)

```js
window.Echo.private('whatsapp-messages')
    .listen('.MessageReceived', (e) => {
        console.log('Nuevo mensaje recibido:', e);
    });
```


# 📁 Configuración en el paquete
En tu archivo config/whatsapp.php asegúrate de tener:
```php
return [
    'broadcast_channel_type' => env('WHATSAPP_BROADCAST_TYPE', 'private'),
];
```

Y en tu .env:
```bash
WHATSAPP_BROADCAST_TYPE=private
```

Recuerde que si decide utilizar canales privados debe utilizar los caneles en routes-channel.php
```php
Broadcast::channel('whatsapp-messages', function ($user) {
    // Puedes personalizar la lógica de acceso aquí
    return $user !== null;
});
```

# 🧪 Prueba de Eventos
Puedes emitir manualmente un evento de prueba con:
```bash
    php artisan tinker
```

```php
    event(new \Scriptdevelop\WhatsappManager\Events\MessageReceived([
        'from' => '51987654321',
        'message' => 'Hola desde Reverb'
    ]));
```

### 🖥️ Escuchar desde el frontend
Canal Privado
```js
    window.Echo.private('whatsapp-messages')
        .listen('.MessageReceived', (e) => {
            console.log('Nuevo mensaje recibido:', e);
        });
```

Canal publico
```js
    window.Echo.channel('whatsapp-messages')
        .listen('.MessageReceived', (e) => {
            console.log('Nuevo mensaje recibido:', e);
        });
```
---

### 📡 **Eventos del Paquete**

El paquete incluye una serie de eventos que se disparan automáticamente en diferentes situaciones. Estos eventos son compatibles con **Laravel Echo** y **Laravel Reverb**, lo que permite escuchar y reaccionar a ellos en tiempo real desde el frontend.

---

#### **Configuración de Eventos**

1. **Configurar el tipo de canal de transmisión:**
   En el archivo whatsapp.php, asegúrate de definir el tipo de canal (`public` o `private`):

   ```php
   return [
       'broadcast_channel_type' => env('WHATSAPP_BROADCAST_CHANNEL_TYPE', 'private'),
   ];
   ```

   En tu archivo `.env`:
   ```bash
   WHATSAPP_BROADCAST_CHANNEL_TYPE=private
   ```

2. **Configurar Laravel Echo o Laravel Reverb:**
   - Instala Laravel Echo y PusherJS:
     ```bash
     npm install --save laravel-echo pusher-js
     ```

   - Configura Echo en `resources/js/bootstrap.js`:
     ```js
     import Echo from 'laravel-echo';

     window.Pusher = require('pusher-js');

     window.Echo = new Echo({
         broadcaster: 'reverb',
         key: import.meta.env.VITE_REVERB_APP_KEY,
         wsHost: import.meta.env.VITE_REVERB_HOST,
         wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
         forceTLS: false,
         enabledTransports: ['ws'],
     });
     ```

   - Asegúrate de tener las variables necesarias en tu `.env` frontend:
     ```bash
     VITE_REVERB_APP_KEY=whatsapp-key
     VITE_REVERB_HOST=127.0.0.1
     VITE_REVERB_PORT=8080
     ```

---

#### **Eventos Disponibles**

A continuación, se describen los eventos disponibles en el paquete, cómo se configuran y cómo escucharlos desde el frontend.

| Evento                       | Canal                | Alias                      |
|------------------------------|----------------------|----------------------------|
| BusinessSettingsUpdated      | whatsapp.business    | business.settings.updated  |
| MessageReceived              | whatsapp.messages    | message.received           |
| MessageDelivered             | whatsapp.status      | message.delivered          |
| MessageRead                  | whatsapp.status      | message.read               |
| TemplateCreated              | whatsapp.templates   | template.created           |
| TemplateApproved             | whatsapp.templates   | template.approved          |
| TemplateRejected             | whatsapp.templates   | template.rejected          |
| InteractiveMessageReceived   | whatsapp.messages    | interactive.received       |
| MediaMessageReceived         | whatsapp.messages    | media.received             |

---

##### **1. `BusinessSettingsUpdated`**

- **Descripción:** Se dispara cuando se actualizan los ajustes de la cuenta empresarial.
- **Canal:** `whatsapp.business`
- **Alias:** `business.settings.updated`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.business')
    .listen('.business.settings.updated', (e) => {
        console.log('Ajustes empresariales actualizados:', e.data);
    });
```

---

##### **2. `MessageReceived`**

- **Descripción:** Se dispara cuando se recibe un mensaje de texto.
- **Canal:** `whatsapp.messages`
- **Alias:** `message.received`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.messages')
    .listen('.message.received', (e) => {
        console.log('Nuevo mensaje recibido:', e.data);
    });
```

---

##### **3. `MessageDelivered`**

- **Descripción:** Se dispara cuando un mensaje es entregado.
- **Canal:** `whatsapp.status`
- **Alias:** `message.delivered`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.status')
    .listen('.message.delivered', (e) => {
        console.log('Mensaje entregado:', e.data);
    });
```

---

##### **4. `MessageRead`**

- **Descripción:** Se dispara cuando un mensaje es leído.
- **Canal:** `whatsapp.status`
- **Alias:** `message.read`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.status')
    .listen('.message.read', (e) => {
        console.log('Mensaje leído:', e.data);
    });
```

---

##### **5. `TemplateCreated`**

- **Descripción:** Se dispara cuando se crea una plantilla.
- **Canal:** `whatsapp.templates`
- **Alias:** `template.created`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.templates')
    .listen('.template.created', (e) => {
        console.log('Plantilla creada:', e.data);
    });
```

---

##### **6. `TemplateApproved`**

- **Descripción:** Se dispara cuando una plantilla es aprobada.
- **Canal:** `whatsapp.templates`
- **Alias:** `template.approved`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.templates')
    .listen('.template.approved', (e) => {
        console.log('Plantilla aprobada:', e.data);
    });
```

---

##### **7. `TemplateRejected`**

- **Descripción:** Se dispara cuando una plantilla es rechazada.
- **Canal:** `whatsapp.templates`
- **Alias:** `template.rejected`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.templates')
    .listen('.template.rejected', (e) => {
        console.log('Plantilla rechazada:', e.data);
    });
```

---

##### **8. `InteractiveMessageReceived`**

- **Descripción:** Se dispara cuando se recibe un mensaje interactivo (botones o listas).
- **Canal:** `whatsapp.messages`
- **Alias:** `interactive.received`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.messages')
    .listen('.interactive.received', (e) => {
        console.log('Mensaje interactivo recibido:', e.data);
    });
```

---

##### **9. `MediaMessageReceived`**

- **Descripción:** Se dispara cuando se recibe un mensaje multimedia (imagen, video, audio, documento, sticker).
- **Canal:** `whatsapp.messages`
- **Alias:** `media.received`

**Ejemplo de uso en el frontend:**
```js
window.Echo.private('whatsapp.messages')
    .listen('.media.received', (e) => {
        console.log('Mensaje multimedia recibido:', e.data);
    });
```

---

#### **Prueba de Eventos**

Puedes emitir manualmente un evento de prueba con Laravel Tinker:

```bash
php artisan tinker
```

```php
event(new \Scriptdevelop\WhatsappManager\Events\MessageReceived([
    'from' => '51987654321',
    'message' => 'Hola desde Reverb'
]));
```

---

Con esta configuración, puedes escuchar y reaccionar a los eventos del paquete desde tu frontend utilizando Laravel Echo o Laravel Reverb. Esto te permite implementar funcionalidades en tiempo real como notificaciones, actualizaciones de estado y más.

## Configuración de Eventos
Configurar el tipo de canal de transmisión: En el archivo whatsapp.php, asegúrate de definir el tipo de canal (public o private):

```php
return [
    'broadcast_channel_type' => env('WHATSAPP_BROADCAST_CHANNEL_TYPE', 'private'),
];
```
En tu archivo .env:
```bash
WHATSAPP_BROADCAST_CHANNEL_TYPE=private
```
Configurar Laravel Echo o Laravel Reverb:

Instala Laravel Echo y PusherJS:
```bash
npm install --save laravel-echo pusher-js
```

Configura Echo en resources/js/bootstrap.js:

```js
import Echo from 'laravel-echo';

window.Pusher = require('pusher-js');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: false,
    enabledTransports: ['ws'],
});

```
Asegúrate de tener las variables necesarias en tu .env frontend:

```bash
VITE_REVERB_APP_KEY=whatsapp-key
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
```



# Eventos Disponibles
A continuación, se describen los eventos disponibles en el paquete, cómo se configuran y cómo escucharlos desde el frontend.


1. BusinessSettingsUpdated
Descripción: Se dispara cuando se actualizan los ajustes de la cuenta empresarial.
Canal: whatsapp.business
Alias: business.settings.updated
Ejemplo de uso en el frontend:
```js
window.Echo.private('whatsapp.business')
    .listen('.business.settings.updated', (e) => {
        console.log('Ajustes empresariales actualizados:', e.data);
    });
```

2. MessageReceived
Descripción: Se dispara cuando se recibe un mensaje de texto.
Canal: whatsapp.messages
Alias: message.received
Ejemplo de uso en el frontend:
```js
window.Echo.private('whatsapp.messages')
    .listen('.message.received', (e) => {
        console.log('Nuevo mensaje recibido:', e.data);
    });
```


3. MessageDelivered
Descripción: Se dispara cuando un mensaje es entregado.
Canal: whatsapp.status
Alias: message.delivered
Ejemplo de uso en el frontend:
```js
window.Echo.private('whatsapp.status')
    .listen('.message.delivered', (e) => {
        console.log('Mensaje entregado:', e.data);
    });
```


4. MessageRead
Descripción: Se dispara cuando un mensaje es leído.
Canal: whatsapp.status
Alias: message.read
Ejemplo de uso en el frontend:
```js
window.Echo.private('whatsapp.status')
    .listen('.message.read', (e) => {
        console.log('Mensaje leído:', e.data);
    });
```


5. TemplateCreated
Descripción: Se dispara cuando se crea una plantilla.
Canal: whatsapp.templates
Alias: template.created
Ejemplo de uso en el frontend:

```js
window.Echo.private('whatsapp.templates')
    .listen('.template.created', (e) => {
        console.log('Plantilla creada:', e.data);
    });
```


6. TemplateApproved
Descripción: Se dispara cuando una plantilla es aprobada.
Canal: whatsapp.templates
Alias: template.approved
Ejemplo de uso en el frontend:
```js
window.Echo.private('whatsapp.templates')
    .listen('.template.approved', (e) => {
        console.log('Plantilla aprobada:', e.data);
    });
```


7. TemplateRejected
Descripción: Se dispara cuando una plantilla es rechazada.
Canal: whatsapp.templates
Alias: template.rejected
Ejemplo de uso en el frontend:

```js
window.Echo.private('whatsapp.templates')
    .listen('.template.rejected', (e) => {
        console.log('Plantilla rechazada:', e.data);
    });
```


8. InteractiveMessageReceived
Descripción: Se dispara cuando se recibe un mensaje interactivo (botones o listas).
Canal: whatsapp.messages
Alias: interactive.received
Ejemplo de uso en el frontend:

```js
window.Echo.private('whatsapp.messages')
    .listen('.interactive.received', (e) => {
        console.log('Mensaje interactivo recibido:', e.data);
    });
```

9. MediaMessageReceived
Descripción: Se dispara cuando se recibe un mensaje multimedia (imagen, video, audio, documento, sticker).
Canal: whatsapp.messages
Alias: media.received
Ejemplo de uso en el frontend:

```js
window.Echo.private('whatsapp.messages')
    .listen('.media.received', (e) => {
        console.log('Mensaje multimedia recibido:', e.data);
    });
```

Prueba de Eventos
Puedes emitir manualmente un evento de prueba con Laravel Tinker:
```bash
php artisan tinker
```

```php
event(new \Scriptdevelop\WhatsappManager\Events\MessageReceived([
    'from' => '51987654321',
    'message' => 'Hola desde Reverb'
]));
```

---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="04-plantillas.md" title="Sección anterior">◄◄ Plantillas</a>
    </td>
    <td align="center">
      <a href="../intro.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
    <td align="right">
      <a href="06-webhook.md" title="Sección siguiente">Webhook ►►</a>
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
