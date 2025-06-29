
---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="06-eventos.md" title="Sección anterior">◄◄ Plantillas</a>
    </td>
    <td align="center">
      <a href="../intro.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentación del Webhook de WhatsApp Manager | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---

## 📡 Documentación del Webhook de WhatsApp

### Introducción
El webhook de WhatsApp es el componente central que recibe y procesa eventos en tiempo real de la API de WhatsApp Business. Este endpoint maneja todos los tipos de interacciones, incluyendo mensajes entrantes, actualizaciones de estado, eventos de plantillas y más.


### 📚 Tabla de Contenidos
1. Configuración del Webhook
2. Verificación del Webhook
3. Estructura de Eventos
4. Tipos de Mensajes Soportados
5. Manejo de Estados
6. Eventos de Plantillas
7. Seguridad
8. Ejemplos de Payloads
9. Registro de Eventos

--- 

## Configuración del Webhook
Para configurar el webhook en tu aplicación de Meta Developers:

1. **Variables de entorno requeridas:**
    ```sh
    WHATSAPP_VERIFY_TOKEN="tu_token_secreto"
    WHATSAPP_API_URL="https://graph.facebook.com"
    WHATSAPP_API_VERSION="v18.0"
    ```
2. **Registrar el endpoint:**
    - URL: https://tudominio.com/whatsapp/webhook
    - Campo de verificación: hub.verify_token
    - Eventos a suscribir:
        - Mensajes
        - Estado de mensajes
        - Plantillas


## Verificación del Webhook
Cuando WhatsApp envía una solicitud GET para verificar el webhook:

```http
GET /whatsapp/webhook?hub.mode=subscribe&hub.challenge=123456789&hub.verify_token=tu_token_secreto
```

El sistema responde con el hub.challenge si el token es válido:

```php
return response()->make($request->input('hub_challenge'), 200);
```

## Estructura de Eventos
Todos los eventos POST tienen esta estructura básica:
```json
{
  "entry": [
    {
      "id": "WEBHOOK_ID",
      "changes": [
        {
          "value": {
            // Datos específicos del evento
          },
          "field": "messages" // o "message_template"
        }
      ]
    }
  ]
}
```

## Tipos de Mensajes Soportados
1. **Mensajes de Texto**
    - Tipo: text
    - Procesamiento:
        - Extrae el contenido de texto
        - Almacena en la tabla messages
        - Dispara evento TextMessageReceived

2. **Mensajes Multimedia**
    - Tipos: image, audio, video, document, sticker
    - Procesamiento:
        1.- Descarga el archivo desde WhatsApp
        2.- Almacena en sistema de archivos
        3.- Guarda referencia en media_files
        4.- Dispara evento MediaMessageReceived

3. **Mensajes Interactivos**
    - Tipos: interactive (botones o listas)
    - Procesamiento:
        - Extrae la selección del usuario
        - Almacena como tipo INTERACTIVE
        - Dispara evento InteractiveMessageReceived

4. **Ubicaciones**
    - Tipo: location
    - Procesamiento:
        - Guarda coordenadas y nombre del lugar
        - Dispara evento LocationMessageReceived

5. **Contactos Compartidos**
    - Tipo: contacts
    - Procesamiento:
        - Extrae nombre, teléfonos y correos
        - Almacena como tipo CONTACT
        - Dispara evento ContactMessageReceived

6. **Reacciones**
    - Tipo: reaction
    - Procesamiento:
        - Vincula al mensaje original
        - Almacena el emoji
        - Dispara evento ReactionReceived

7. **Mensajes del Sistema**
    - Tipo: system
    - Casos:
        - **Cambio de número de usuario**
        - **Actualizaciones de cuenta**
  
8. **Manejo de recepcion mensajes no Soportados**
    - Tipo: Unsupported
    - Casos:
        - Videos circulares
        - Mensajes de Encuestas
        - Mensajes de eventos

---

## Manejo de Estados
**Actualiza el estado de mensajes enviados:**

#### Estado
- **delivered** - "Actualiza delivered_at y dispara MessageDelivered"
- **read** - Actualiza read_at y dispara MessageRead
- **failed** - 	Actualiza failed_at y dispara MessageFailed
- **opt-out** - Marca contacto como no acepta marketing (código 131050)

--- 

## Eventos de Plantillas
Maneja el ciclo de vida de plantillas:

#### Evento
- **APPROVED** - Actualiza estado y crea nueva versión
- **REJECTED** - Registra motivo de rechazo
- **PENDING** - Marca como pendiente de revisión
- **CREATE** - Crea nueva plantilla en base de datos
- **UPDATE** - Actualiza plantilla y crea nueva versión
- **DELETE** - Eliminación suave de plantilla
- **DISABLE** - Deshabilita plantilla y versiones


---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="06-eventos.md" title="Sección anterior">◄◄ Plantillas</a>
    </td>
    <td align="center">
      <a href="../intro.md" title="Tabla de contenido">▲ Tabla de contenido</a>
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
