# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Soporte BSUID (Business-Scoped User ID):** Implementación completa del nuevo identificador BSUID de WhatsApp, efectivo desde el 31 de marzo de 2026. El BSUID es único por usuario/portfolio y reemplaza al `wa_id` cuando el usuario activa la función de nombre de usuario.
- **Campo `bsuid` en contactos:** Nueva columna `bsuid` (varchar 150, única, indexada) en la tabla de contactos para persistir el identificador BSUID. También se añadieron `parent_bsuid` (para portfolios vinculados) y `username` (nombre de usuario de WhatsApp).
- **Campos BSUID en mensajes:** Nuevas columnas `from_bsuid`, `from_parent_bsuid`, `recipient_bsuid` y `parent_recipient_bsuid` en la tabla de mensajes para registrar el origen y destino por BSUID.
- **Campo `username` en perfiles de contacto:** Nueva columna `username` en `whatsapp_contact_profiles` para reflejar el nombre de usuario de WhatsApp del contacto.
- **Resolución de contactos BSUID-first:** El procesador de webhooks ahora resuelve contactos priorizando `bsuid`, con fallback a `wa_id` para contactos previos al 31/03/2026. Crea contactos nuevos cuando solo existe el BSUID (usuarios con nombre de usuario activo).
- **Campos opcionales `wa_id` y `from`:** Las columnas `phone_number`, `country_code` en contactos y `message_from` en mensajes ahora aceptan `NULL` para soportar mensajes de usuarios cuyo número de teléfono está oculto.
- **Infraestructura interna para envío por BSUID:** `sendViaApi()` (método privado) ya construye el payload con `recipient` cuando se le pasa un BSUID en lugar de `to`. Los métodos públicos de envío (`sendTextMessage`, `sendImageMessage`, etc.) aún no exponen este parámetro — la API pública de BSUID será habilitada por WhatsApp en mayo 2026 y se completará en ese momento.
- **Soporte de bloqueo/desbloqueo por BSUID:** `BlockService` detecta automáticamente si el identificador es un BSUID (formato `CC.XXXXX`) o un número de teléfono y construye el payload correcto (`user_id` vs `user`).
- **Nuevo evento `BusinessUsernameUpdated`:** Se dispara cuando WhatsApp notifica un cambio de estado en el nombre de usuario del negocio (`business_username_update`). Canal: `whatsapp.business`, alias: `business.username.updated`.
- **Nuevo tipo de mensaje de sistema `user_changed_user_id`:** Soporte para el webhook que WhatsApp envía cuando un usuario cambia su número de teléfono y su BSUID es regenerado. Persiste el nuevo BSUID en el contacto.
- **Nuevo servicio `UsernameService`:** Gestión del nombre de usuario del negocio en WhatsApp. Métodos: `setUsername()`, `getUsername()`, `deleteUsername()`, `getUsernameSuggestions()`.
- **Nuevo DTO `MessageResponse` extendido:** Ahora incluye `waId`, `bsuid`, `parentBsuid` y `recipientId` para reflejar la estructura completa de la respuesta de la API de mensajes con soporte BSUID.
- **Actualización de BSUID en webhooks de estado:** `handleStatusUpdate()` actualiza automáticamente el campo `bsuid` y `parent_bsuid` del contacto cuando el webhook de estado incluye el campo `contacts`.
- **Helpers en modelo `Contact`:** Nuevo método `getBsuidOrWaId()` que retorna el identificador disponible (BSUID preferido sobre `wa_id`). Los métodos `blockOn()` y `unblockOn()` lo usan automáticamente.

### Added
- **Webhooks Overrides:** Soporte explícito e integrado para reescribir webhooks a nivel WABA y número de teléfono de forma opcional (` WhatsappService::overrideWabaWebhook()`, `WhatsappService::overridePhoneWebhook()`, etc.).
- **Nativo WhatsApp Flows (Send):** Inyección para armar dinámicamente JSONs de mensajes interactivos tipo Flow (`MessageDispatcherService::sendViaApi()`). Incluye el helper público de primer nivel `sendInteractiveFlowMessage()`.
- **Configuración Global de Flows:** Los JSONs de compilación en `FlowBuilder` ya no usan valores fijos. Ahora implementan la versión `7.3` para el framework de interfaz y `3.0` como Data API directamente leyendo desde la variable central `config('whatsapp.flows.*')`.
- **Clonado y Redirecciones de Endpoint:** Extensión de interfaz fluida en `FlowBuilder` para incrustar `->cloneFlowId()` y `->endpointUri()` en las llamadas iniciales del REST hacia Meta.
- **Endpoint Meta Migrate Flows (`MIGRATE_FLOWS`):** Se mapeó la constante dentro de la capa `Endpoints.php` y se abstrajo la llamada interna en `FlowService::migrateFlows()` para forzar transiciones operativas de templates entre WABAs distintas.
- **Clonado y Redirecciones de Endpoint:** Extensión de interfaz fluida en `FlowBuilder` para incrustar `->cloneFlowId()` y `->endpointUri()` en las llamadas iniciales del REST hacia Meta.
- **Endpoint Meta Migrate Flows (`MIGRATE_FLOWS`):** Se mapeó la constante dentro de la capa `Endpoints.php` y se abstrajo la llamada interna en `FlowService::migrateFlows()` para forzar transiciones operativas de templates entre WABAs distintas.
- **Refresco de Tokens Visuales de Flujos (`Get Preview URL`):** Agregada la macro utilitaria `FlowService::getFreshPreviewUrl()` para inyectar forzadamente `fields=preview.invalidate(true)`, regenerar previsualizaciones y persistirlas en base de datos.
- **Ciclo de Vida Extendido de Flows (Delete, Deprecate, Assets):** Incorporación de las utilidades vitales `FlowService::delete()` (borra de Meta y de base local), `FlowService::deprecate()` (cambia el status a DEPRECATED preservando modelo) y `FlowService::getFlowAssets()` (obtención de URLs de archivos publicadas).
- **Soporte para envíos Draft de Flows:** Inyección del parámetro `mode` estructurado en `MessageDispatcherService`
- Soporte para Códigos QR (`/message_qrdls`) mediante el Wrapper Facade `Whatsapp::qrCode()`. Permite sincronizar colecciones remotas, crear, actualizar pre-textos y depurar en cascada.
- Modelo Eloquent `WhatsappQrCode` anidado al número telefónico y autodescubierto en el service container.
- Soporte oficial para enviar Flows en versión `Draft` utilizando el parámetro `mode` en la capa de despacho (útil para QA manual de Flows no publicados).
- Capacidad fluida de inyectar botones de formulario a plantillas remotamente utilizando Nombres y Data JSON mediante `addFlowButtonByName` y `addFlowButtonByJson` en el Builder.
- Dispatch dinámico de inyección contextual mediante la llave de action `flow_token` y `flow_action_data` para template interaction views.
- **Creación polimórfica de Botones Flow en Plantillas:** Refactorización de `TemplateBuilder` inyectando los constructores `addFlowButtonByName()` y `addFlowButtonByJson()` (además de soporte dinámico para `navigate_screen`).
- **Despacho Avanzado de Plantillas para Flows:** Creación del inyector `addFlowActionData()` en `TemplateMessageBuilder` para atar tokens (`flow_token`) y payload dinámico (`flow_action_data`) al momento exacto de enviar una plantilla tipo FLOW a un usuario final.
- **Soporte de Reactivación de Plantillas (Unpause):** Funcionalidad `TemplateService::unpauseTemplate()` añadida para reanudar campañas que hayan sido penalizadas por Facebook por interacciones negativas (vía API `/{template_id}/unpause`).
- **Soporte de Monitoreo de Calidad:** Funcionalidad `TemplateService::getQualityScore()` añadida para vigilar el estado interactivo (`GREEN`, `YELLOW`, `RED`, `UNKNOWN`) de una plantilla antes de que Meta aplique pausas en caliente (`/{template_id}?fields=quality_score`).
- **Anulación Dinámica de URL en Plantillas:** Agregado método constructor `TemplateMessageBuilder::addTapTargetConfiguration()` para sobrescribir (en tiempo real de envío) los botones de destino de enlaces promocionales para plantillas CTA sin re-crear templates fijos.
