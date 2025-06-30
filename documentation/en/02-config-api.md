---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="01-instalacion.md" title="Previous section">◄◄ Installation</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Table of contents">▲ Table of contents</a>
    </td>
    <td align="right">
      <a href="03-mensajes.md" title="Next section: Message Sending">Message Management ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>WhatsApp Manager Webhook Documentation | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a></sub>
</div>

---

## 🚀 🧩 API Configuration

### 🔑 Meta Credentials
To integrate your application with WhatsApp Business API, you need to configure Meta credentials in your environment:

### Essential Requirements

1. Access Token: Access token with permissions:
    - whatsapp_business_management
    - whatsapp_business_messaging
    - Obtained from Meta Developer Panel

2. Business Account ID: Unique ID of your business account:
    - Located at: Business Settings > Accounts > WhatsApp Accounts

3. Phone Number ID: Identifier for your WhatsApp business number:
    - Location: WhatsApp Tools > API and webhooks > Settings

>⚠️ Important:
>Ensure you configure the variables in the .env file

```sh
# Basic configuration
WHATSAPP_API_URL=https://graph.facebook.com
WHATSAPP_API_VERSION=v21.0
WHATSAPP_ACCESS_TOKEN=your-access-token-here
```

---
## 1. Business Account Registration

- **Register a business account in WhatsApp Business API.**
    Registers and synchronizes WhatsApp business accounts with their associated phone numbers.
    - Makes a request to the WhatsApp API, retrieves account data, and stores it in the database. This method gets account data, WhatsApp phone numbers associated with the account, and each phone number's profile.
    - Used to retrieve data from the API and store it in the database.
  
> ⚠️**Observations:**
> - Requires a valid access token with `whatsapp_business_management` permissions.
> - The `business_id` must be the numeric ID of your WhatsApp Business Account.

  ```php
  use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

  $account = Whatsapp::account()->register([
      'api_token' => '***********************',
      'business_id' => '1243432234423'
  ]);
  ```