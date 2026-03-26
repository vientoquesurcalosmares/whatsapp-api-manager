# Propuestas Futuras — WhatsApp API Manager

Este documento registra funcionalidades planificadas para versiones futuras del paquete,
orientadas principalmente a **proveedores de tecnología de Meta (BSP)** y operadores
multi-tenant que gestionan múltiples WABAs de terceros.

---

## 1. Gestión avanzada de WABAs (para BSP / multi-tenant)

### Contexto
Los Business Solution Providers (BSP) de Meta gestionan WABAs en nombre de terceros.
Esto implica flujos de autenticación distintos (OAuth de sistema, tokens por WABA),
permisos delegados y ownership de cuentas que no son propias.

### Qué falta implementar

#### 1.1 Registro insertado (Embedded Signup)
El flujo OAuth que permite a un cliente autorizar al BSP a gestionar su WABA.
- Generar URL de autorización con los scopes correctos
- Recibir y persistir el `access_token` por WABA
- Renovación de tokens de sistema

**Archivos a crear:**
- `src/Services/EmbeddedSignupService.php`
- Migración: campo `oauth_token` en `whatsapp_business_accounts` (separado de `api_token`)

#### 1.2 Compartir WABA con socios via API
`POST /<WABA_ID>/assigned_users` — asignar permisos a otro Business Manager.
`DELETE /<WABA_ID>/assigned_users` — revocar permisos.

**Archivos a crear:**
- Métodos en `WhatsappService` o nuevo `WabaManagementService`

#### 1.3 Líneas de crédito (Credit Lines)
Los BSP comparten su línea de crédito con los clientes para que puedan enviar mensajes.
- `POST /<CREDIT_LINE_ID>/whatsapp_credit_sharing_and_attach` — adjuntar
- `DELETE /<WABA_ID>/whatsapp_credit_sharing_and_attach` — desadjuntar

**Archivos a crear:**
- `src/Services/CreditLineService.php`
- Migración: tabla `whatsapp_credit_lines` o campo en `whatsapp_business_accounts`

---

## 2. Webhooks de cuenta (account_update)

### Contexto
WhatsApp envía el webhook `account_update` cuando una WABA infringe una política,
cambia de estado, o cuando el límite de mensajes cambia. Actualmente el paquete
no maneja este webhook de forma estructurada.

### Qué falta implementar

- Handler `handleAccountUpdate(array $value)` en `BaseWebhookProcessor`
- Casos: `ACCOUNT_VIOLATION`, restricciones de mensajería, cambios de tier
- Evento `AccountViolationDetected` (ShouldBroadcast)
- Evento `MessagingLimitUpdated` (ShouldBroadcast)

**Archivos a modificar:**
- `src/Services/WebhookProcessors/BaseWebhookProcessor.php`

**Archivos a crear:**
- `src/Events/AccountViolationDetected.php`
- `src/Events/MessagingLimitUpdated.php`

---

## 3. Envío por BSUID — API pública (mayo 2026)

### Contexto
WhatsApp habilitará el envío de mensajes usando el BSUID como destinatario
(campo `recipient` en lugar de `to`) en mayo de 2026. La infraestructura interna
de `sendViaApi()` ya está preparada.

### Qué falta implementar
Actualizar todos los métodos públicos de envío para aceptar `?string $bsuid = null`:
- `sendTextMessage()`
- `sendImageMessage()`
- `sendAudioMessage()`
- `sendVideoMessage()`
- `sendDocumentMessage()`
- `sendStickerMessage()`
- `sendLocationMessage()`
- `sendContactMessage()`
- `sendReactionMessage()`
- `sendInteractiveMessage()` y variantes
- `sendTemplateMessage()`

Además, `resolveContact()` debe aceptar el caso BSUID-only (sin teléfono) y crear
el contacto correctamente.

**Archivos a modificar:**
- `src/Services/MessageDispatcherService.php`

**Consideración de backward compat:** Todos los parámetros `$bsuid` deben ser opcionales
y al final de la firma para no romper llamadas existentes.

---

## 4. Migración de números de teléfono entre WABAs

### Contexto
Meta permite migrar un número de teléfono de una WABA a otra. Es un proceso
con pasos: solicitar migración, verificar OTP, confirmar. Relevante para BSP
que mueven clientes entre cuentas.

### Qué falta implementar
- `POST /<PHONE_NUMBER_ID>/migrate` — iniciar migración
- Estado de migración en `WhatsappPhoneNumber`
- Manejo del webhook de confirmación

**Complejidad:** Alta. Requiere gestión de estado multi-paso.

---

## 5. Permisos y roles por WABA (multi-tenant)

### Contexto
En un sistema multi-tenant donde múltiples clientes comparten la instalación del paquete,
es necesario aislar qué usuarios pueden gestionar qué WABAs y números de teléfono.

### Qué falta implementar
- Integración opcional con Spatie Laravel Permission o sistema de permisos propio
- Middleware `WhatsappAccountAccess` que valide que el usuario autenticado tiene
  acceso a la WABA que intenta operar
- Scope global en modelos para filtrar por WABA del usuario autenticado

**Archivos a crear:**
- `src/Middleware/WhatsappAccountAccess.php`
- Documentación de integración con sistemas de permisos

---

## 6. Panel de administración (opcional)

### Contexto
Para instalaciones multi-cliente, un panel web para gestionar WABAs, números,
plantillas y perfiles sin necesidad de código.

### Consideraciones
- Livewire o Inertia/Vue (a definir según stack del cliente)
- Publicable vía `php artisan vendor:publish --tag=whatsapp-views`
- Sería un paquete separado (`whatsapp-api-manager-ui`) para no inflar el core

---

## Prioridad sugerida

| # | Feature | Impacto | Complejidad | Prioridad |
|---|---------|---------|-------------|-----------|
| 3 | Envío por BSUID (mayo 2026) | Alto | Baja | **P1** |
| 2 | Webhooks account_update | Alto | Media | **P1** |
| 1.1 | Embedded Signup | Alto | Alta | **P2** |
| 1.2 | Compartir WABA con socios | Medio | Media | **P2** |
| 1.3 | Líneas de crédito | Alto (BSP) | Media | **P2** |
| 4 | Migración de números | Medio | Alta | **P3** |
| 5 | Permisos multi-tenant | Alto (BSP) | Alta | **P3** |
| 6 | Panel de administración | Medio | Muy alta | **P4** |
