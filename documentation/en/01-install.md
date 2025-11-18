
---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="../../README.md" title="Previous section: Home">◄◄ Home</a>
    </td>
    <td align="center">
      <a href="00-content.md" title="Table of contents">▲ Table of contents</a>
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
   - Main configuration (config/whatsapp.php).
   - Logging configuration (config/logging.php).
   - Package core configuration.
        
    ```bash
    php artisan vendor:publish --tag=whatsapp-config
    ```

3. **Configure logging (config/logging.php)**:
    Add the whatsapp channel.
    - In the "config/logging.php" file, you must add a new channel for the package logs.
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
    This command will publish the package migrations. It's not necessary to publish them since running "php artisan migrate" will take the migrations directly from the package. If you wish, you can publish them and edit them as needed.

    ```bash
    php artisan vendor:publish --tag=whatsapp-migrations
    ```

5. **Publish routes (required)**:
    This command will publish the webhook routes file. It's mandatory since it's needed to receive incoming messaging notifications.

    ```bash
    php artisan vendor:publish --tag=whatsapp-routes
    ```

6. **Exclude webhook from CSRF (bootstrap/app.php)**:
    You must exclude the webhook routes from CSRF. In the "bootstrap/app.php" file.

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

    # OPTIONALS VARIABLES
    META_CLIENT_ID=123456789012345
    META_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    META_REDIRECT_URI=https://yourdomain.com/meta/callback
    META_SCOPES=whatsapp_business_management,whatsapp_business_messaging
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
>The seeders are required for working with WhatsApp templates

---

## **📁 Media Files Configuration:**

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
    - Events to subscribe:
        - messages
        - message_statuses
        - message_template_status_update (optional)

> ⚠️ Important:
>For local development, you can use the ngrok tool described below.

**Configuration summary:**

| Parameter         | Recommended Value                                  |
|-------------------|---------------------------------------------------|
| Webhook URL       | `https://yourdomain.com/whatsapp-webhook`          |
| Verify Token      | Value of `WHATSAPP_VERIFY_TOKEN` in your `.env`   |
| Events            | `messages`, `message_statuses`                    |




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
    
    ngrok http --host-header=rewrite 8000
    ```
4. Use the URL generated by ngrok as your webhook in Meta:
    ```sh
    https://xxxxxx.ngrok.io/whatsapp-webhook
    ```


## 🔍 Final Validation
**After completing installation, verify:**

1. Routes are published and accessible.
2. Verify token matches in .env and Meta.
3. Media directories have write permissions.
4. Storage symbolic link works correctly.
5. Selected events in Meta cover your needs.

>💡 Tip:
>To test the configuration, send a test message to your WhatsApp Business number and verify it appears in the logs (storage/logs/whatsapp.log).




## Model and Webhook Customization

**Table of Contents**
1. Model Customization
2. Webhook Customization
3. Advanced Examples
4. Troubleshooting


**Model Customization**
**📊 Introduction**
The WhatsApp API Manager package allows complete customization of database models to adapt to your application's structure. You can extend, modify, or replace any package model.

**🔧 Basic Configuration**
To customize a model, modify the config/whatsapp.php file:

```php
'models' => [
    'contact' => \App\Models\CustomContact::class,
    'message' => \App\Models\CustomMessage::class,
    // ... other models
],
```

**🛠 Create a Custom Model**
1. Extend the base model (recommended):

```php
namespace App\Models;

use ScriptDevelop\WhatsappManager\Models\Contact as BaseContact;

class CustomContact extends BaseContact
{
    protected $table = 'custom_contacts';
    
    // Add custom relationships
    public function customOrders()
    {
        return $this->hasMany(Order::class, 'contact_id');
    }
    
    // Override existing methods
    public function someMethod()
    {
        // Custom logic
    }
}
```

2. Create a completely new model (advanced):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Contracts\WhatsappContactInterface;

class CustomContact extends Model implements WhatsappContactInterface
{
    // Implement all methods required by the interface
}
```


## 📋 Custom Migrations
If you change the table structure, create a custom migration:

```bash
php artisan make:migration modify_contacts_table
```

```php
public function up()
{
    Schema::table('contacts', function (Blueprint $table) {
        $table->string('custom_field')->nullable();
        $table->index('custom_field');
    });
}
```

## 🔄 Update Configuration
After creating your custom models, update the configuration:

```php
// config/whatsapp.php
'models' => [
    'contact' => \App\Models\CustomContact::class,
    'message' => \App\Models\CustomMessage::class,
    // ... other custom models
],
```

# Webhook Customization

## 🌐 Introduction

  Webhook processing can be completely customized to adapt to specific business logic, integrations with other systems, or special handling of certain message types.


## 🚀 Publish Base Processor
Run the command to publish the base processor:

```bash
php artisan whatsapp:publish-webhook-processor
```
This will create the app/Services/WhatsappWebhookProcessor.php file.

The command automatically updates your configuration:


```php
// config/whatsapp.php
'webhook' => [
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'processor' => \App\Services\Whatsapp\WhatsappWebhookProcessor::class,
],
```

## 💻 Basic Customization

```php
namespace App\Services;

use ScriptDevelop\WhatsappManager\Services\WebhookProcessors\BaseWebhookProcessor;

class WhatsappWebhookProcessor extends BaseWebhookProcessor
{
    public function handle($request)
    {
        // Custom logic before processing
        \Log::info('Webhook received', $request->all());
        
        // Standard processing
        return parent::handle($request);
        
        // Or completely custom processing
    }
}
```

## 🎯 Customization Examples
1. Specific processing for certain messages:


```php
protected function processTextMessage(array $message, $contact, $whatsappPhone)
{
    // Custom logic before standard processing
    if (str_contains($message['text']['body'], 'keyword')) {
        $this->handleSpecialCommand($message, $contact);
        return null; // Don't save to database
    }
    
    // Standard processing
    return parent::processTextMessage($message, $contact, $whatsappPhone);
}
```


2. Integration with other systems:

```php
protected function handleIncomingMessage(array $message, ?array $contact, ?array $metadata)
{
    // Send to external system before processing
    $this->sendToExternalSystem($message, $contact);
    
    // Standard processing
    parent::handleIncomingMessage($message, $contact, $metadata);
    
    // Actions after processing
    $this->triggerPostProcessing($message);
}

private function sendToExternalSystem($message, $contact)
{
    // Integration with CRM, ERP, etc.
    Http::post('https://api.your-system.com/webhook', [
        'message' => $message,
        'contact' => $contact
    ]);
}
```

3. Media processing with AI:

```php
protected function processMediaMessage(array $message, $contact, $whatsappPhone)
{
    // Special processing for images
    if ($message['type'] === 'image') {
        return $this->processImageWithAI($message, $contact, $whatsappPhone);
    }
    
    // Standard processing for other media types
    return parent::processMediaMessage($message, $contact, $whatsappPhone);
}
```


## 🔌 Custom Events
You can fire custom events in your processor:

```php
protected function fireTextMessageReceived($contactRecord, $messageRecord)
{
    // Standard event
    parent::fireTextMessageReceived($contactRecord, $messageRecord);
    
    // Custom event
    event(new \App\Events\CustomTextMessageReceived($contactRecord, $messageRecord));
}
```


## Advanced Examples
**🤖 Ticket System Integration**

```php
protected function processTextMessage(array $message, $contact, $whatsappPhone)
{
    $text = $message['text']['body'];
    
    // Automatically create ticket for certain words
    if (preg_match('/support|help|problem/i', $text)) {
        $ticket = Ticket::create([
            'contact_id' => $contact->id,
            'description' => $text,
            'source' => 'whatsapp'
        ]);
        
        // Notify team
        Notification::send($ticket->assignedTeam, new NewTicketNotification($ticket));
    }
    
    return parent::processTextMessage($message, $contact, $whatsappPhone);
}
```

## 🛒 Order Processing

```php
protected function processInteractiveMessage(array $message, $contact, $whatsappPhone)
{
    $interactiveType = $message['interactive']['type'];
    
    if ($interactiveType === 'button_reply') {
        $buttonId = $message['interactive']['button_reply']['id'];
        
        // Handle product selection
        if (str_starts_with($buttonId, 'product_')) {
            $productId = str_replace('product_', '', $buttonId);
            $this->addToCart($contact, $productId);
        }
    }
    
    return parent::processInteractiveMessage($message, $contact, $whatsappPhone);
}
```


# Troubleshooting
## ❌ Error: "Class not found"
**If you encounter class not found errors:**

1. Verify that namespaces in your configuration are correct
2. Run composer dump-autoload
3. Verify that classes exist in the specified location

## 🔄 Reset to Default Configuration
To revert to default models:

```php
// config/whatsapp.php
'models' => [
    'contact' => \ScriptDevelop\WhatsappManager\Models\Contact::class,
    // ... other default models
],
```


# 📞 Support
**If you need help with customization:**

1. Review examples in the documentation
2. Check issues on GitHub
3. Create a new issue with details of your implementation

Note: Always test your customizations in a development environment before implementing them in production. Advanced customizations may affect package functionality.




<br>

---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="../../README.md" title="Previous section: Home">◄◄ Home</a>
    </td>
    <td align="center">
      <a href="00-content.md" title="Table of contents">▲ Table of contents</a>
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




