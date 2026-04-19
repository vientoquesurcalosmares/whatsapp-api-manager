# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.55] - 2026-04-19

### Changed
- **FlowService**: Mejorada la propagación de excepciones en el método `publishFlow` para preservar el tipo de excepción original y facilitar el debugging.
- **Versión**: Bump de versión a 1.1.55.

## [1.1.54] - 2026-04-07

### Refactor
- **Endpoints de Encriptación**: Simplificación de la construcción de URLs en `FlowService` delegando el prefijo de URL base y versión al `ApiClient`.

### Changed
- **Consulta de Llaves de Encriptación**: Se añadió el parámetro `fields` (`business_public_key,business_public_key_status`) a los métodos `getBusinessPublicKeyStatus` y `getPhoneNumberPublicKeyStatus` para asegurar que Meta retorne los metadatos completos de la llave.

## [1.1.53] - 2026-04-07

### Added
- **Soporte Completo Multi-Tenant para Encriptado de Flows**:
  - Comando `php artisan whatsapp:generate-keys`: Nueva opción `--account-id=` para guardar el par de claves RSA en un almacenamiento seguro particionado (`storage/app/whatsapp/flows/keys/{accountId}`) evitando usar el `public` disk.
  - `BaseWebhookProcessor`: Añadido hook dinámico escalable `resolvePrivateKeyPath(Request $request)` que permite inyectar dinámicamente y al vuelo la clave RSA desde tu base de datos o almacenamiento aislado antes de desencriptar Data Channels multi-tenant.

## [1.1.52] - 2026-04-07

### Added
- **Soporte Multi-Tenant en FlowCryptoService**: Refactorización para permitir cargar llaves privadas `.pem` a nivel de cuenta (`loadForAccount()`) o ruta personalizada (`loadFromPath()`), abandonando el formato de inyección forzada del singleton. Mantiene compatibilidad hacia atrás mediante `ensureKeyLoaded()` usando el path legacy.

## [1.1.51] - 2026-04-06

### Added
- **Gestión de Llaves Públicas para Flow Endpoints**: Añadidos métodos `getPhoneNumberPublicKeyStatus()` y `setPhoneNumberPublicKey()` en `FlowService` para la gestión y carga de la llave asimétrica (`business_public_key`) en WhatsApp Business, requerida obligatoriamente para desencriptar peticiones de Data Exchange hacia tu Webhook.

## [1.1.50] - 2026-04-06

### Fixed
- **Migraciones de Estadísticas de Flujo**: Añadido `Schema::dropIfExists('whatsapp_flow_screen_stats')` en la migración para garantizar la idempotencia al ejecutar o refrescar las migraciones.

## [1.1.49] - 2026-04-06

### Fixed
- **Migraciones de Estadísticas de Flujo**: Corrección en nombre de clave única compuesta en la migración `create_whatsapp_flow_screen_stats_table` para no exceder el límite de 64 caracteres de MySQL (`flow_screen_stats_unique`).

## [1.1.48] - 2026-04-06

### Fixed
- **Configuración de Acciones de Flow**: Corrección en migración de `whatsapp_flow_actions` permitiendo que el campo `config` sea nulo a nivel base de datos, y establecimiento de default attribute a array vacío en el modelo `WhatsappFlowAction`.

## [1.1.47] - 2026-04-06

### Added
- **Arquitectura de WhatsApp Flows Endpoint (Data Channel):** Implementación integral para habilitar los endpoints de flujos (Data Exchange).
  - Interfaces `FlowEndpointHandlerInterface` y `FlowActionHandlerInterface`.
  - `FlowEndpointRouter` y `FlowActionDispatcher` para delegar de forma dinámica las peticiones según la configuración persistida en el WABA.
  - Modelos `WhatsappFlowEndpointConfig` y `WhatsappFlowAction`.
- **Persistencia de Sesiones y Analíticas de Flow:**
  - Migraciones para enriquecimiento de `whatsapp_flow_sessions` (datos de contexto y finalización stateful) y `whatsapp_flow_responses`.
  - Tabla de métricas de pantallas: `whatsapp_flow_screen_stats` y modelo `WhatsappFlowScreenStats` para monitorizar abandono y éxito en multi-step flows.
  - Captura y registro de sesión desde Webhooks de manera pasiva (`FlowSessionService`).
- **Evento de Sistema:** Emisión automática del evento `FlowSessionCompleted` desde `BaseWebhookProcessor` al culminar el ciclo nfm_reply final.
- **Integración fluida en `TemplateEditor`:** Extensión del método `addFlowButton()` con parámetros explícitos para soporte `DATA_EXCHANGE`, `navigateScreen` y `flowIcon`.
- **Integración de Servicios y Providers:** Carga de los nuevos controladores, inyector de dependencias (IoC/DI bindings) en `WhatsappServiceProvider` y configuración extendida en `whatsapp.php`.

## [1.1.46] - 2026-04-05

### Added
- **`FlowMediaService::processFlowMedia()`** — procesamiento completo de archivos multimedia enviados por `PhotoPicker` / `DocumentPicker` en el flujo `nfm_reply`. Recibe un ítem `{ id, file_name, mime_type, sha256 }`, llama a Meta Graph API para obtener `cdn_url` + `encryption_metadata`, y ejecuta el algoritmo de validación y descifrado completo definido por Meta.
- **`FlowMediaService::processInlineMedia()`** — procesamiento para el caso del `data_exchange` endpoint, donde el ítem ya contiene `cdn_url` y `encryption_metadata` inline (sin necesidad de llamar a la API de Meta).
- **`FlowMediaService::fetchMediaMetadata()`** — llamada a `GET /{media_id}` en Meta Graph API con Bearer token del número de teléfono para recuperar `cdn_url` y `encryption_metadata`.

### Changed
- **`FlowMediaService` reescrito completamente** con el algoritmo correcto según la especificación oficial de Meta para archivos del CDN de WhatsApp:
  1. `SHA256(cdn_file) == encrypted_hash` — valida integridad del archivo descargado.
  2. `HMAC-SHA256(hmac_key, iv || ciphertext)[0:10] == hmac10` — valida autenticidad (los últimos 10 bytes del archivo CDN son el HMAC truncado).
  3. `AES-256-CBC(encryption_key, iv, ciphertext)` + pkcs7 unpadding automático — descifra el contenido.
  4. `SHA256(decrypted) == plaintext_hash` — valida integridad del archivo descifrado.
- **`handleFlowResponseMessage()` reescrito** con soporte dual para ambas estructuras de respuesta de Meta:
  - **nfm_reply**: `photo_picker` / `document_picker` como arrays de `{ id, file_name, mime_type, sha256 }` — requiere llamada a Meta API por `media_id`.
  - **data_exchange endpoint**: ítem con `cdn_url` + `encryption_metadata` inline.
  - El `$whatsappPhone` se resuelve desde `metadata['phone_number_id']` (consistente con todos los demás handlers del webhook processor). Funciona con contactos BSUID y no-BSUID sin cambios, ya que la identificación del teléfono de negocio siempre viene en `metadata`.
  - Los archivos procesados se inyectan en `flow_data` bajo la clave `{campo}_files` para acceso directo desde el evento de finalización.
  - Log de warning cuando el phone no se puede resolver, consistente con `handleEditMessage`, `handleRevokeMessage` y `handleSystemMessage`.

## [1.1.45] - 2026-04-04

### Added
- **`FlowEditor::setRawJsonStructure(string $jsonString): self`** — nuevo método para inyectar el JSON de un flow ya encodificado como string. Permite que editores visuales externos (o cualquier sistema que genere el JSON completo) suban el JSON directamente a Meta sin que PHP lo re-parsee como array y pierda los objetos vacíos `{}`. Cuando está seteado, `save()` omite `buildFlowJson()` y usa el string tal como está.

### Changed
- **`FlowEditor::save()` refactorizado:** reemplaza la implementación con cURL directo por el `ApiClient` del paquete (multipart upload), siendo consistente con `FlowBuilder::save()`. Admite dos rutas: (1) JSON raw via `setRawJsonStructure()` — preserva `{}` correctamente; (2) JSON construido via `buildFlowJson()` — flujo de edición fluida existente. La actualización de metadatos es ahora condicional — solo se dispara si `name` está seteado.
- **`TemplateBuilder::addFlowButton()` — nuevo parámetro `$flowIcon`:** Se agrega `?string $flowIcon = null`. Valores válidos: `DEFAULT`, `DOCUMENT`, `PROMOTION`, `REVIEW`. Valores no reconocidos se ignoran silenciosamente.

### Fixed
- **Objetos vacíos `{}` en JSON de Flows:** PHP convertía `data: {}` y `payload: {}` en arrays vacíos al pasar por `json_decode($json, true)` + `json_encode`. Meta rechazaba el JSON con *"Expected property 'data' to be of type 'object' but found 'array'"*. Resuelto con `setRawJsonStructure()` que acepta el string pre-encodificado sin re-parseo.

## [1.1.44] - 2026-04-02

### Changed
- **Documentación técnica exhaustiva de botones Flow:** Sección de *Crear Plantillas Atadas a WhatsApp Flows* en `04-plantillas.md` completamente reescrita. Ahora incluye: pre-requisito de status del flujo, tabla de parámetros por método, tabla de errores comunes y ejemplos completos para las tres variantes (`addFlowButton`, `addFlowButtonByName`, `addFlowButtonByJson`).
- **PHPDoc mejorado en `TemplateBuilder`:** Los métodos `addFlowButtonByName()` y `addFlowButtonByJson()` ahora tienen documentación inline exhaustiva con descripción de parámetros, valores esperados, excepciones posibles y ejemplos de uso.

## [1.1.43] - 2026-04-01

### Changed
- **`addFlowButtonByName()` robusto con validación real:** El método ahora busca el flujo en la base de datos local por nombre exacto usando `WhatsappModelResolver::flow()`. Valida que el flujo exista, que no haya duplicados (si los hay, lanza excepción con los `wa_flow_id` disponibles para usar `addFlowButton()` en su lugar), y que el flujo esté en estado `approved` o `published` antes de construir el botón. El payload ahora envía el `flow_id` real (no el `flow_name`) para compatibilidad correcta con la API de Meta.

## [1.1.42] - 2026-04-01

### Fixed
- **Valor de `flowAction` corregido a mayúsculas en botones Flow:** El valor por defecto del parámetro `$flowAction` en los métodos `addFlowButton()`, `addFlowButtonByName()` y `addFlowButtonByJson()` de `TemplateBuilder` era `'navigate'` (minúsculas), lo cual es rechazado por la API de Meta. Corregido a `'NAVIGATE'` (mayúsculas) para cumplir con la especificación oficial.

## [1.1.41] - 2026-04-01

### Fixed
- **Detección automática de formato de imagen QR:** El método `downloadImage()` de `QrCodeService` ya no depende únicamente del parámetro `$format` para determinar la extensión del archivo a guardar. Ahora inspecciona el `Content-Type` del response HTTP de Meta para detectar si la imagen enviada es `image/svg+xml` o `image/png`, asignando correctamente la extensión y el campo `qr_image_format` en el modelo. Si el Content-Type no es concluyente, cae de vuelta al parámetro recibido como fallback.

## [1.1.40] - 2026-04-01

### Fixed
- **Campo `code` faltante en endpoints QR:** Los métodos `syncAll()` y `get()` de `QrCodeService` no incluían el campo `code` en los `fields` solicitados a Meta. Esto causaba que el hash identificador del QR no llegara en la respuesta y el modelo quedara con el campo `code` vacío. Se añadió `code` al listado de fields en ambos endpoints.

## [1.1.39] - 2026-04-01

### Changed
- **Automatización de Descargas QR:** El método `downloadImage()` fue refactorizado para requerir que la URL de Meta (`qr_image_url`) ya exista en la base. A cambio, los métodos de persistencia `create()`, `get()` y `syncAll()` ahora interceptan y ejecutan la descarga física de la imagen de forma nativa y automática. Esto permite popular el atributo local en disco `qr_image_path` inmediatamente tras crear o recuperar el QR sin requerir procesos paralelos.

## [1.1.38] - 2026-04-01

### Added
- **Descarga Física de Códigos QR:** Incorporado el método `Whatsapp::qrCode()->downloadImage($phoneNumberId, $codigoHash, $format)` para descargar activamente el código QR desde Meta (vía URL firmada) y persistirlo automágicamente en disco local mediante `Storage::disk('public')`. El modelo `WhatsappQrCode` ahora almacena correctamente la ruta en disco en `qr_image_path`.
- **Limpieza Ecológica de QRs:** El método `delete()` de `QrCodeService` fue extendido para revisar e interceptar el disco duro; ahora borra simultáneamente el archivo físico SVG/PNG remanente antes de eliminar el modelo local en base de datos.

## [1.1.37] - 2026-04-01

### Fixed
- **Error 403 en obtención de cuenta:** Se extrajo el campo `primary_funding_id` de la petición general de `getBusinessAccount()`, ya que Meta requiere permisos de BSP (*Business Solution Provider*) para accederlo, bloqueando el registro a usuarios normales. Ahora se solicita mediante el nuevo método `getBusinessAccountFundingId()` gestionado de forma segura ("fail-safe") en el proceso de registro.

## [1.1.36] - 2026-04-01

### Added
- **Soporte de Métodos de Pago en Cuenta:** Campo `primary_funding_id` añadido al modelo `WhatsappBusinessAccount` y sincronizado en el alta de cuenta y actualización. Agregado el método utilitario `$account->hasPaymentMethod()` para validar rápidamente si la cuenta tiene tarjeta de crédito asociada.

## [1.1.35] - 2026-04-01

### Added
- **Eventos BSUID (`UserIdUpdated`):** Alta del evento y el respectivo procesamiento de webhooks (`user_id_update`) cuando un contacto cambia su identificador BSUID de forma remota.
- **Optimizaciones en Templates:** Mejoras en `TemplateBuilder` y `TemplateMessageBuilder` para el parseo de constructores comerciales y despachos de mensajes encapsulados.

## [1.1.34] - 2026-04-01

### Fixed
- **FlowService:** Eliminación del campo `json_structure` de los `fields` solicitados a la API de Meta (líneas 67 y 166) para prevenir errores HTTP 400.
- **WhatsappTemplateController:** La sincronización de flows ahora es un efecto secundario seguro (*safety net*). Si falla la llamada a Meta durante el `show()`, la excepción se loguea suavemente y la página de templates carga con los datos locales en lugar de fallar.

## [1.1.33] - 2026-04-01

### Added
- **Plantillas Commerce Avanzadas (Creación):** Inyección de constructores paramétricos interactivos en `TemplateBuilder`. Soporte pleno para generación automática vía Meta de `addCarousel(Closure)`, `addLimitedTimeOffer()`, `addCatalogButton()`, `addMpmButton()`, `addSpmButton()`, `addCopyCodeButton()`.
- **Plantillas Commerce Avanzadas (Envío):** Resolutores de variables anidadas en `TemplateMessageBuilder` vía subclases fluidas. Añadidos `CommerceSectionBuilder` y `CarouselMessageBuilder` para permitir closures profundos como `addCarouselCards(Closure)` y `addMpmButton(title, Closure)`. Permite enviar secuencias de carrusel (hasta 10 tarjetas) o productos MPM (30 skus) limpiamente.
- **Soporte SPM y Tiempo Limitado (LTO):** Nuevos inyectores en el payload final `addHeaderProduct(product, catalog)` y `addLimitedTimeOfferExpiration(ms)`.

### Fixed
- **Perfil empresarial opcional en registro:** `AccountRegistrationService::processPhoneNumberProfile()` ya no bloquea el registro cuando Meta no permite acceder al perfil (error #131000 u otro). Se loguea como warning y continúa.

## [1.1.32] - 2026-04-01

### Added
#### Perfil de empresa y cuenta oficial
- **Actualización de perfil de empresa:** `WhatsappService::updateBusinessProfile()` — POST a `/{phone_number_id}/whatsapp_business_profile`. Actualiza `about`, `address`, `description`, `email`, `vertical`, `profile_picture_handle` y `websites`. Sincroniza campos escalares en la BD local tras éxito.
- **Actualización de foto de perfil de empresa en un paso:** `WhatsappService::updateBusinessProfilePicture()` — recibe la ruta local del archivo, gestiona internamente la sesión de carga y el upload a Meta, y aplica el handle resultante al perfil.
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

### Changed
- **Refactor QrCodeService:** Uso de named parameters de PHP 8 para solicitudes HTTP POST, mejorando la seguridad y legibilidad en las llamadas a la API.

## [1.1.31] - 2026-03-30

### Changed
- **Desacople Arquitectónico en FlowService:** Se han removido las restricciones de tipos estrictos que obligaban a inyectar directamente `WhatsappBusinessAccount` y `WhatsappFlow` (comentando esos _use_). Ahora se ha flexibilizado a `Illuminate\Database\Eloquent\Model` para permitir usar modelos customizados o sobreescritos sin reventar por un fatal error de PHP.

## [1.1.30] - 2026-03-30

### Added
- **Fusión de la rama Main / PR 97 (Gestión Media de Plantillas Local):** Automatización de descargas en caliente de recursos provenientes de Meta a través de webhooks para el modelo `TemplateVersionMediaFile`.

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
