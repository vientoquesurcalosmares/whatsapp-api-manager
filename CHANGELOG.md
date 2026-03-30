# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.31] - 2026-03-30

### Changed
- **Desacople Arquitectónico en FlowService:** Se han removido las restricciones de tipos estrictos que obligaban a inyectar directamente `WhatsappBusinessAccount` y `WhatsappFlow` (comentando esos _use_). Ahora se ha flexibilizado a `Illuminate\Database\Eloquent\Model` para permitir usar modelos customizados o sobreescritos sin reventar por un fatal error de PHP.

## [1.1.30] - 2026-03-30

### Added
- **Fusión de la rama Main / PR 97 (Gestión Media de Plantillas Local):** Automatización de descargas en caliente de recursos provenientes de Meta a través de webhooks para el modelo `TemplateVersionMediaFile`.
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
