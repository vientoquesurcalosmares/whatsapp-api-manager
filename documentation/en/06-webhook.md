
---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="05-events.md" title="Previous section">◄◄ Events</a>
    </td>
    <td align="center">
      <a href="00-content.md" title="Table of contents">▲ Table of contents</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>WhatsApp Manager Webhook Documentation | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a></sub>
</div>

---

## 📡 WhatsApp Webhook Documentation

### Introduction
The WhatsApp webhook is the central component that receives and processes real-time events from the WhatsApp Business API. This endpoint handles all types of interactions, including incoming messages, status updates, template events, and more.


### 📚 Table of Contents
1. Webhook Configuration
2. Webhook Verification
3. Event Structure
4. Supported Message Types
5. Status Handling
6. Template Events
7. Security
8. Payload Examples
9. Event Logging

--- 

## Webhook Configuration
To configure the webhook in your Meta Developers application:

1. **Required environment variables:**
    ```sh
    WHATSAPP_VERIFY_TOKEN="your_secret_token"
    WHATSAPP_API_URL="https://graph.facebook.com"
    WHATSAPP_API_VERSION="v18.0"
    ```
2. **Register the endpoint:**
    - URL: https://yourdomain.com/whatsapp/webhook
    - Verification field: hub.verify_token
    - Events to subscribe:
        - Messages
        - Message statuses
        - Templates


## Webhook Verification
When WhatsApp sends a GET request to verify the webhook:

```http
GET /whatsapp/webhook?hub.mode=subscribe&hub.challenge=123456789&hub.verify_token=your_secret_token
```

The system responds with the hub.challenge if the token is valid:

```php
return response()->make($request->input('hub_challenge'), 200);
```

## Event Structure
All POST events have this basic structure:
```json
{
  "entry": [
    {
      "id": "WEBHOOK_ID",
      "changes": [
        {
          "value": {
            // Event-specific data
          },
          "field": "messages" // or "message_template"
        }
      ]
    }
  ]
}
```

## Supported Message Types
1. **Text Messages**
    - Type: text
    - Processing:
        - Extracts text content
        - Stores in messages table
        - Fires TextMessageReceived event

2. **Media Messages**
    - Types: image, audio, video, document, sticker
    - Processing:
        1. Downloads file from WhatsApp
        2. Stores in file system
        3. Saves reference in media_files
        4. Fires MediaMessageReceived event

3. **Interactive Messages**
    - Types: interactive (buttons or lists)
    - Processing:
        - Extracts user selection
        - Stores as INTERACTIVE type
        - Fires InteractiveMessageReceived event

4. **Locations**
    - Type: location
    - Processing:
        - Saves coordinates and place name
        - Fires LocationMessageReceived event

5. **Shared Contacts**
    - Type: contacts
    - Processing:
        - Extracts name, phones, and emails
        - Stores as CONTACT type
        - Fires ContactMessageReceived event

6. **Reactions**
    - Type: reaction
    - Processing:
        - Links to original message
        - Stores the emoji
        - Fires ReactionReceived event

7. **System Messages**
    - Type: system
    - Cases:
        - **User number change**
        - **Account updates**
  
8. **Unsupported Message Handling**
    - Type: Unsupported
    - Cases:
        - Circular videos
        - Poll messages
        - Event messages

---

## Status Handling
**Updates the status of sent messages:**

#### Status
- **delivered** - "Updates delivered_at and fires MessageDelivered"
- **read** - Updates read_at and fires MessageRead
- **failed** - Updates failed_at and fires MessageFailed
- **opt-out** - Marks contact as marketing opt-out (code 131050)

--- 

## Template Events
Handles template lifecycle:

#### Event
- **APPROVED** - Updates status and creates new version
- **REJECTED** - Records rejection reason
- **PENDING** - Marks as pending review
- **CREATE** - Creates new template in database
- **UPDATE** - Updates template and creates new version
- **DELETE** - Soft deletes template
- **DISABLE** - Disables template and versions


---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="05-events.md" title="Previous section">◄◄ Events</a>
    </td>
    <td align="center">
      <a href="00-content.md" title="Table of contents">▲ Table of contents</a>
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

