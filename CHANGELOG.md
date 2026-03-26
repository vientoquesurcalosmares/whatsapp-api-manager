# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

#### Perfil de empresa y cuenta oficial
- **Actualización de perfil de empresa:** `WhatsappService::updateBusinessProfile()` — POST a `/{phone_number_id}/whatsapp_business_profile`. Actualiza `about`, `address`, `description`, `email`, `vertical`, `profile_picture_handle` y `websites`. Sincroniza campos escalares en la BD local tras éxito.
- **Solicitar cambio de nombre visible:** `WhatsappService::updateDisplayName()` — POST `new_display_name` al endpoint del número. Persiste el nombre y estado `PENDING_REVIEW` en BD hasta que el webhook `phone_number_name_update` notifique la decisión final.
- **Consultar nombre visible en revisión:** `WhatsappService::getDisplayNamePendingStatus()` — GET `new_display_name` y `new_name_status` desde la API; sincroniza en BD.
- **Campos `new_display_name` y `new_name_status`** en `whatsapp_phone_numbers` para reflejar el estado de la solicitud de nombre pendiente de aprobación.
- **Solicitar Cuenta de Empresa Oficial (OBA):** `WhatsappService::requestOfficialBusinessAccount()` — POST a `/{phone_number_id}/official_business_account` con supporting links, país de operación, marca principal e idioma.
- **Consultar estado OBA:** `WhatsappService::getOfficialBusinessAccountStatus()` — GET `oba_status` e `is_official_business_account` desde la API; sincroniza en BD.
- **Campo `oba_status`** en `whatsapp_phone_numbers` — valores posibles: `NOT_STARTED`, `PENDING`, `APPROVED`, `REJECTED`.
- **GET WABA enriquecido:** `getBusinessAccount()` ahora solicita `currency`, `country` y `status` además de los campos existentes.
- **Constante `Endpoints::OFFICIAL_BUSINESS_ACCOUNT`** — `{phone_number_id}/official_business_account`.
- **`FUTURE_PROPOSALS.md`** — roadmap de funcionalidades planificadas para BSP y operadores multi-tenant (Embedded Signup, líneas de crédito, permisos multi-tenant, etc.).

#### BSUID — Business-Scoped User ID (efectivo 31/03/2026)
- **Soporte BSUID:** Implementación completa del nuevo identificador BSUID de WhatsApp. Es único por usuario/portfolio y reemplaza al `wa_id` cuando el usuario activa la función de nombre de usuario.
- **Campo `bsuid` en contactos:** Nueva columna `bsuid` (varchar 150, única, indexada). También `parent_bsuid` (para portfolios vinculados) y `username` (nombre de usuario de WhatsApp).
- **Campos BSUID en mensajes:** `from_bsuid`, `from_parent_bsuid`, `recipient_bsuid` y `parent_recipient_bsuid`.
- **Campo `username` en perfiles de contacto:** Nueva columna `username` en `whatsapp_contact_profiles`.
- **Resolución de contactos BSUID-first:** El webhook processor resuelve contactos priorizando `bsuid`, con fallback a `wa_id` para contactos existentes. Crea contactos nuevos cuando solo existe el BSUID.
- **Campos opcionales:** `phone_number`, `country_code` en contactos y `message_from` en mensajes ahora aceptan `NULL` — necesario para usuarios con número oculto.
- **Infraestructura para envío por BSUID:** `sendViaApi()` construye payload con `recipient` cuando se provee BSUID. Los métodos públicos de envío se actualizarán cuando WhatsApp habilite la API en mayo 2026.
- **Bloqueo/desbloqueo por BSUID:** `BlockService` detecta automáticamente formato BSUID (`CC.XXXXX`) y construye el payload correcto (`user_id` vs `user`).
- **Nuevo evento `BusinessUsernameUpdated`:** Webhook `business_username_update`. Canal: `whatsapp.business`, alias: `business.username.updated`.
- **Soporte `user_changed_user_id`:** Nuevo tipo de mensaje de sistema que se dispara cuando un usuario cambia de número y su BSUID se regenera. Persiste el nuevo BSUID en el contacto.
- **`UsernameService`:** Gestión del nombre de usuario del negocio — `setUsername()`, `getUsername()`, `deleteUsername()`, `getUsernameSuggestions()`.
- **`MessageResponse` DTO extendido:** Nuevos campos `waId`, `bsuid`, `parentBsuid` y `recipientId` leídos de `contacts[0]` en la respuesta de la API.
- **BSUID en webhooks de estado:** `handleStatusUpdate()` actualiza `bsuid` y `parent_bsuid` del contacto cuando el webhook incluye el array `contacts`.
- **`Contact::getBsuidOrWaId()`:** Helper que retorna BSUID si existe, `wa_id` si no. Usado por `blockOn()` y `unblockOn()`.

#### Funcionalidades previas
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
