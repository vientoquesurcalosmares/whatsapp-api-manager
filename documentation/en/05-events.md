---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="04-plantillas.md" title="Previous section">◄◄ Templates</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Table of contents">▲ Table of contents</a>
    </td>
    <td align="right">
      <a href="06-webhook.md" title="Next section">Webhook ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>WhatsApp Manager Webhook Documentation | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">View on GitHub</a></sub>
</div>

---

## 📡 Real-Time Events

### Introduction
The real-time event system allows your application to instantly react to WhatsApp interactions using WebSockets. With Laravel Reverb and Laravel Echo integration, you can receive instant notifications about incoming messages, status updates, template events, and more, creating highly interactive and responsive user experiences.

**Key benefits:**
- Instant notifications without polling
- Real-time UI updates
- Lower latency for better user experience
- Seamless integration with Laravel ecosystem
- Support for public and private channels

### 📚 Table of Contents

1. Laravel Reverb Setup
    - Installation
    - Server configuration
    - Environment variables

2. Laravel Echo Setup
    - Frontend dependencies
    - Frontend configuration
    - Vite environment variables

3. Supported Events
    - Messages
    - Statuses
    - Templates
    - Interactions

4. Listening to Events
    - Channel configuration
    - Frontend examples
    - Testing with Tinker

5. Best Practices
    - Channel security
    - Error handling
    - Performance optimization

# 📦 Laravel Reverb Installation
### 1. Install Laravel Reverb via Composer
In a new terminal, run:
```php
composer require laravel/reverb
```


