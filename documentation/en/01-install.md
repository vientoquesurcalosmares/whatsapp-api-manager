---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="../../README.md" title="Previous section: Home">◄◄ Home</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Table of contents">▲ Table of contents</a>
    </td>
    <td align="right">
      <a href="02-config-api.md" title="Next section">Configure API ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>WhatsApp Manager Webhook Documentation | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a></sub>
</div>

---
## 🚀 Complete Installation

### 📋 Prerequisites
Before installing the package, you'll need a WhatsApp API Cloud account:

> **📹 Recommended tutorials:**
> - [How to get a free account - AdBoostPro](https://www.youtube.com/watch?v=of6dEsKSh-0)
> - [Initial setup - Bismarck Aragón](https://www.youtube.com/watch?v=gdD_0ernIqM)

---

### 🔧 Installation Steps

1. **Install the package via Composer**:
    ```bash
    composer require scriptdevelop/whatsapp-manager
    ```

2. **Publish configuration files**:
    This command will publish the package's base configuration files:
   - Main configuration (config/whatsapp.php)
   - Logging configuration (config/logging.php)
   - Package core configuration
        
    ```bash
    php artisan vendor:publish --tag=whatsapp-config
    ```

3. **Configure logging (config/logging.php)**:
    Add the whatsapp channel:
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

4. **Publish migrations (optional)**:
    This command will publish the package migrations. Note that running `php artisan migrate` will automatically use the package migrations. Publish only if you want to customize them.

    ```bash
    php artisan vendor:publish --tag=whatsapp-migrations
    ```

5. **Publish webhook routes (required)**:
    This command publishes the webhook routes file, which is mandatory for receiving incoming messages.

    ```bash
    php artisan vendor:publish --tag=whatsapp-routes
    ```

6. **Exclude webhook from CSRF protection (bootstrap/app.php)**:
    Add the webhook route to CSRF exceptions:
    ```php
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/whatsapp-webhook',
        ]);
    })
    ```

7. **Configure environment variables (.env)**:
    ```sh
    WHATSAPP_API_URL=https://graph.facebook.com
    WHATSAPP_API_VERSION=v21.0
    WHATSAPP_VERIFY_TOKEN=your-verify-token
    WHATSAPP_USER_MODEL=App\Models\User
    WHATSAPP_BROADCAST_CHANNEL_TYPE=private
    ```
---

## **🗃️ Database Configuration:**

1. **Run migrations:**
    ```sh
    php artisan migrate
    ```

2. **Publish and run language seeders:**
    ```sh
    php artisan vendor:publish --tag=whatsapp-seeders
    php artisan db:seed --class=WhatsappTemplateLanguageSeeder
    ```

>⚠️ Important:
>Seeders are required for working with WhatsApp templates

---

## **📁 Media Storage Configuration:**

1. **Create directory structure:**
    ```sh
    storage/app/public/whatsapp/
    ├── audios/
    ├── documents/
    ├── images/
    ├── stickers/
    └── videos/
    ```

2. **Publish automatic structure (optional):**
    ```sh
    php artisan vendor:publish --tag=whatsapp-media
    ```

3. **Create symbolic link:**
    ```sh
    php artisan storage:link
    ```

---

## **🔗 Meta Webhook Configuration:**

**Follow these steps to configure webhooks in the Meta Developers platform:**

1. Access Meta for Developers
2. Select your application
3. Navigate to Products > WhatsApp > Configuration
4. In the Webhooks section:
    - Webhook URL: https://yourdomain.com/whatsapp-webhook
    - Verify Token: Value of WHATSAPP_VERIFY_TOKEN in your .env
    - Subscribe to events:
        - messages
        - message_statuses
        - message_template_status_update (optional)

> ⚠️ Important:
>For local development, you can use the ngrok tool described below.

**Configuration summary:**

| Parameter         | Recommended Value                              |
|-------------------|-----------------------------------------------|
| Webhook URL       | `https://yourdomain.com/whatsapp-webhook`     |
| Verify Token      | Value of `WHATSAPP_VERIFY_TOKEN` in `.env`    |
| Events            | `messages`, `message_statuses`                |

## **🛠️ Ngrok - Local Development Tools:**
**Using ngrok for local testing:**
1. Download ngrok from ngrok.com
2. Run your local server:
    ```sh
    php artisan serve
    ```
3. Expose your local server:
    ```sh
    ngrok http http://localhost:8000
    ```
4. Use the ngrok-generated URL as your webhook in Meta:
    ```sh
    https://xxxxxx.ngrok.io/whatsapp-webhook
    ```

## 🔍 Final Validation
**After completing installation, verify:**

1. Routes are published and accessible
2. Verify token matches in .env and Meta
3. Media directories have write permissions
4. Storage symbolic link works correctly
5. Selected events in Meta cover your needs

>💡 Tip:
>To test the configuration, send a test message to your WhatsApp Business number and verify it appears in the logs (storage/logs/whatsapp.log).

<br>

---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="../../README.md" title="Previous section: Home">◄◄ Home</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Table of contents">▲ Table of contents</a>
    </td>
    <td align="right">
      <a href="02-config-api.md" title="Next section">Configure API ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>WhatsApp Manager Webhook Documentation | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a></sub>
</div>

---

## ❤️ Support

If you find this project useful, consider supporting its development:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donate%20via-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

## 📄 License

MIT License - See [LICENSE](LICENSE) for details