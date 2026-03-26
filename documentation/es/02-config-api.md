
---

<div align="center">
  <table>
    <tr>
      <td align="left">
        <a href="01-instalacion.md" title="Sección anterior">◄◄ Instalacion</a>
      </td>
      <td align="center">
        <a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
      </td>
      <td align="right">
        <a href="03-mensajes.md" title="Sección siguiente: Envío de Mensajes">Gestión de Mensajes ►►</a>
      </td>
    </tr>
  </table>
</div>

<div align="center">
  <sub>Documentación del Webhook de WhatsApp Manager | 
    <a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a>
  </sub>
</div>

---

## 🚀 🧩 Configuración de API

### Tabla de Contenido

🚀 Configuración de API

🔑 Credenciales de Meta

1. Registro de Cuentas de Negocios

2. Obtener Detalles de Números de Teléfono

3. Registrar número de teléfono

4. Eliminar número de teléfono

5. Bloquear, desbloquear y listar usuarios

6. Gestión de Suscripciones a Webhooks

  - Suscripción Manual

  - Suscripción con Campos Personalizados

7. Sobreescritura de Webhooks (Webhook Overrides)

8. Configuración de Códigos de País

9. Perfil de Empresa

10. Nombre Visible

11. Cuenta de Empresa Oficial (OBA)


### 🔑 Credenciales de Meta
Para integrar tu aplicación con WhatsApp Business API, necesitas configurar las credenciales de Meta en tu entorno:

### Requisitos esenciales

1. Access Token: Token de acceso con permisos:
    - whatsapp_business_management
    - whatsapp_business_messaging
    - Se obtiene desde el Panel de Desarrolladores de Meta

2. Business Account ID: ID único de tu cuenta empresarial:
    - Se encuentra en: Business Settings > Accounts > WhatsApp Accounts

3. Phone Number ID: Identificador de tu número de WhatsApp empresarial:
    - Ubicación: Herramientas de WhatsApp > API y webhooks > Configuración

> ⚠️ Importante:
> Asegurece de configurar las variables en el .env

```sh
# Configuración básica
WHATSAPP_API_URL=https://graph.facebook.com
WHATSAPP_API_VERSION=v21.0
WHATSAPP_ACCESS_TOKEN=your-access-token-here
```

---

## 1. Registro de Cuentas de Negocios.

- **Registra una cuenta de negocios en WhatsApp Business API.**
  Registra y sincroniza cuentas empresariales de WhatsApp con sus números de teléfono asociados.
  - Se hace la peticion a la API de whatsapp, se obtienen los datos de la cuenta y se almacenan en la base de datos. Este metodo obtiene los datos de la cuenta, los telefonos de whatsapp asociados a la cuenta y el perfil de cada numero de telefono.
  - Se usa para Obtener los datos desde la API y alojarlos en la base de datos.

> ⚠️**Observations:**
> - Requires a valid access token with `whatsapp_business_management` permissions.
> - The `business_id` must be the numeric ID of your WhatsApp Business Account.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Al registrar una cuenta, automáticamente se suscriben los webhooks configurados
$account = Whatsapp::account()->register([
    'api_token' => '***********************',
    'business_id' => '1243432234423'
]);

// Durante el registro también se:
// - Registran automáticamente todos los números de teléfono asociados
// - Suscriben los webhooks configurados por defecto
// - Configuran los perfiles de negocio
```

## 2. Obtener Detalles de Números de Teléfono
**Obtén información detallada sobre un número de teléfono registrado.**

- Se hace la peticion a la API de whatsapp para obtener detalles del numero de whatsapp y se almacenan en la base de datos, si el numero ya existe actualiza la informacion.

  Obtén y administra los números de teléfono asociados a una cuenta de WhatsApp Business.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Obtener todos los números asociados a una cuenta empresarial (por Business ID)
$phones = Whatsapp::phone()
    ->forAccount('4621942164157') // Business ID
    ->getPhoneNumbers('4621942164157');

$phoneDetails = Whatsapp::phone()->getPhoneNumberDetails('564565346546');
```

> **Notas:**
> - Utiliza siempre el **Phone Number ID** para realizar operaciones sobre números de teléfono.
> - El **Business ID** se emplea únicamente para identificar la cuenta empresarial.

## Registrar número de teléfono

Puedes registrar un nuevo número de teléfono en tu sistema para asociarlo a una cuenta de WhatsApp Business. Esto es útil para gestionar múltiples números y recibir notificaciones específicas por cada uno.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Registra un nuevo número de teléfono en tu base de datos local
$newPhone = Whatsapp::phone()->registerPhoneNumber('BUSINESS_ACCOUNT_ID', [
    'id' => 'NUEVO_PHONE_NUMBER_ID'
]);
```

- **Nota:** Este proceso solo agrega el número a tu sistema local, no crea el número en Meta. El número debe existir previamente en la cuenta de WhatsApp Business en Meta.

---

## Eliminar número de teléfono

Puedes eliminar un número de teléfono de tu sistema si ya no deseas gestionarlo o recibir notificaciones asociadas a él. Esto ayuda a mantener tu base de datos limpia y actualizada.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Elimina el número de teléfono de tu sistema local
Whatsapp::phone()->deletePhoneNumber('PHONE_NUMBER_ID');
```

- **Importante:**  
  - Eliminar un número solo lo remueve de tu sistema local, **no lo elimina de la cuenta de Meta**.
  - Los Phone Number IDs son diferentes a los Business Account IDs.
  - Para que los webhooks funcionen correctamente, asegúrate de que tus endpoints sean accesibles mediante HTTPS válido.

---

**Resumen:**
- Usa estos métodos para sincronizar y limpiar los números de teléfono que gestionas localmente.
- Los cambios aquí no afectan la configuración de números en la plataforma de Meta, solo en tu aplicación.
- Mantén tus endpoints de webhook actualizados para recibir notificaciones de los números activos.

## Bloquear, desbloquear y listar usuarios de whatsapp
Con estas funciones puede bloquear, desbloquear y listar los numeros de los clientes o usuarios que desida.

**Características Principales**
- Bloqueo de usuarios: Impide que números específicos envíen mensajes a tu WhatsApp Business
- Desbloqueo de usuarios: Restaura la capacidad de comunicación de números previamente bloqueados
- Listado de bloqueados: Obtén información paginada de todos los números bloqueados
- Sincronización automática: Mantiene tu base de datos sincronizada con el estado real en WhatsApp
- Gestión de contactos: Vincula automáticamente los bloqueos con tus contactos existentes

```php
// Bloquear usuarios (con formato automático)
$response = Whatsapp::block()->blockUsers(
    $phone->phone_number_id,
    ['3135694227', '57 3012345678']
);

// Desbloquear usuarios (con reintento automático)
$response = Whatsapp::block()->unblockUsers(
    $phone->phone_number_id,
    ['573137181908']
);

// Listar bloqueados con paginación
$blocked = Whatsapp::block()->listBlockedUsers(
    $phone->phone_number_id,
    50,
    $cursor // Usar cursor real de respuesta previa
);
```

**Observaciones Importantes**

**1. Formato de Números**
Los números se normalizan automáticamente a formato internacional

Ejemplos de conversión:
3135694227 → 573135694227 (para Colombia)
57 3012345678 → 573012345678
+1 (555) 123-4567 → 15551234567

**2. Manejo de Errores**
- Validación previa: No se realizan operaciones redundantes
- Reintento automático: Para operaciones de desbloqueo que requieren método alternativo
- Persistencia condicional: Solo se actualiza la base de datos si la API responde con éxito

**3. Paginación**
Use los cursores de la respuesta para navegar entre páginas:

```php
// Primera página
$page1 = Whatsapp::block()->listBlockedUsers($phoneId, 50);

// Segunda página
$page2 = Whatsapp::block()->listBlockedUsers(
    $phoneId,
    50,
    $page1['paging']['cursors']['after']
);
```

**4. Vinculación con Contactos**
- Se crean automáticamente registros de contacto si no existen
- Los bloqueos se asocian con el modelo Contact
- Estado de marketing actualizado al bloquear:
  - accepts_marketing = false
  - marketing_opt_out_at = now()

**Métodos Adicionales**

Verificar estado de bloqueo

```php
$contact = Contact::find('contact_123');
$isBlocked = $contact->isBlockedOn($phone->phone_number_id);
```

Bloquear/Desbloquear desde el modelo Contact

```php
$contact->blockOn($phone->phone_number_id);
$contact->unblockOn($phone->phone_number_id);
```

# Gestión de Suscripciones a Webhooks de WhatsApp

## 🛠 Configuración

---

## 1. Suscripción Manual con Configuración por Defecto
Puedes sobrescribir la configuración de suscripción utilizando variables de entorno para adaptar los campos y parámetros según tus necesidades. El siguiente ejemplo muestra cómo suscribirte manualmente a los webhooks de WhatsApp usando los valores configurados por defecto en tu aplicación:

```php
use ScriptDevelop\WhatsappManager\Services\WhatsappService;

$whatsappService = app(WhatsappService::class);

// Suscribe la aplicación a los webhooks usando los campos predeterminados
$response = $whatsappService
  ->forAccount('tu_business_account_id')
  ->subscribeApp('whatsapp_business_id');

// Verifica el resultado de la suscripción
if (isset($response['success'])) {
  echo "Suscripción exitosa";
} else {
  echo "Error en suscripción: " . ($response['error']['message'] ?? 'Desconocido');
}
```

Esta operación permite que tu cuenta empresarial reciba notificaciones automáticas de eventos relevantes, como mensajes entrantes, actualizaciones de estado y cambios en la calidad del número, según los campos definidos en la configuración.

---

## 2. Suscripción con Campos Personalizados Durante Registro
- Podras pasar como parametro los webhooks a los que desea suscribir su cuenta.

```php
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;

$registrationService = app(AccountRegistrationService::class);

$accountData = [
    'api_token' => 'tu_token_de_api',
    'business_id' => 'tu_whatsapp_business_id',
];

// Definir campos específicos para suscribir durante el registro
$customFields = [
    'messages',                    // Solo mensajes entrantes
    'message_deliveries',          // Solo entregas
    'message_template_status_update', // Solo estado de plantillas
];

$account = $registrationService->register($accountData, $customFields);
```

- Si no se pasa como parametros se usaran por defecto los que esten en el archivo de configuracion
- En tu archivo config/whatsapp-manager.php, configura los campos suscritos por defecto:

```php
'webhook' => [
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'processor' => \ScriptDevelop\WhatsappManager\Services\WebhookProcessors\BaseWebhookProcessor::class,
    
    // Campos suscritos por defecto
    'subscribed_fields' => [
        'messages',                         // Mensajes entrantes
        'message_deliveries',              // Confirmaciones de entrega
        'message_reads',                   // Confirmaciones de lectura
        'message_template_status_update',  // Estado de plantillas
        'phone_number_quality_update',     // Calidad del número
        'account_update',                  // Actualizaciones de cuenta
        'account_review_update',           // Revisiones de cuenta
        'business_capability_update',      // Capacidades del negocio
        'flows',                           // Flujos de WhatsApp
    ],
],
```
---

# 7. Sobreescritura de Webhooks (Webhook Overrides)

La API de Meta permite definir una URL de webhook distinta a la configurada globalmente en el panel de tu App. Esto es sumamente útil para arquitecturas multi-tenant (SaaS) donde cada cliente (o número telefónico) debe enviar sus eventos a una URL específica.

El paquete expone métodos para sobrescribir (y eliminar la sobreescritura) tanto a nivel de cuenta (WABA) como a nivel de un número de teléfono individual.

> **Importante:**
> - El orden de prioridad de Meta es: **Número Telefónico** -> **WABA** -> **App**.
> - En todos los casos, **debes instanciar primero el contexto de tu cuenta** utilizando `Whatsapp::account()->forAccount('ID_LOCAL_DE_LA_WABA')` o instanciándote el `WhatsappService` y usando `$whatsappService->forAccount(...)`.

## 7.1 Sobreescritura a Nivel WABA (Toda la cuenta)

Utiliza este método si quieres que absolutamente todos los números pertenecientes a esta WhatsApp Business Account apunten al nuevo webhook.

```php
use ScriptDevelop\WhatsappManager\Services\WhatsappService;

$whatsappService = app(WhatsappService::class);

// 1. Establecer el contexto de la WABA
$whatsappService->forAccount('ID_LOCAL_O_BUSINESS_ID');

// 2. Establecer la nueva URL del webhook
$response = $whatsappService->overrideWabaWebhook(
    'https://tu-dominio.com/api/webhooks/waba-cliente-1',
    'tu_verify_token_seguro'
);

// 3. Restaurar al webhook original de la App
$whatsappService->removeWabaWebhookOverride();
```

## 7.2 Sobreescritura a Nivel de Número de Teléfono

Ideal si gestionas números de diferentes sucursales o clientes bajo una misma WABA pero necesitas que cada uno envíe sus eventos a una URL única.

```php
use ScriptDevelop\WhatsappManager\Services\WhatsappService;

$whatsappService = app(WhatsappService::class);
$whatsappService->forAccount('ID_LOCAL_O_BUSINESS_ID');

// El parámetro phoneNumberId debe coincidir con el ID local en tu tabla `whatsapp_phone_numbers`
$idLocalDelNumero = 15; 

// 1. Establecer el webhook específico para el número
$response = $whatsappService->overridePhoneWebhook(
    $idLocalDelNumero,
    'https://tu-dominio.com/api/webhooks/numero-15',
    'token_seguro_del_numero'
);

// 2. Eliminar la URL específica y heredar nuevamente de WABA o de la App
$whatsappService->removePhoneWebhookOverride($idLocalDelNumero);
```

---

# 8. Configuración de Códigos de País

El paquete incluye un sistema flexible para gestionar códigos de país que se utiliza durante el registro de números de teléfono para extraer correctamente el código de país y el número local.

## Configuración Básica

En tu archivo config/whatsapp-manager.php, puedes agregar códigos de país personalizados:

```php
'custom_country_codes' => [
    // Agrega aquí los códigos de país personalizados
    // Formato: 'código_numérico' => 'código_alpha_2'
    '57' => 'CO',  // Colombia
    '1'  => 'US',  // Estados Unidos
    '52' => 'MX',  // México
    '34' => 'ES',  // España
    '54' => 'AR',  // Argentina
    '55' => 'BR',  // Brasil
    '56' => 'CL',  // Chile
    '51' => 'PE',  // Perú
    '58' => 'VE',  // Venezuela
    '593' => 'EC', // Ecuador
    '507' => 'PA', // Panamá
    '506' => 'CR', // Costa Rica
    '502' => 'GT', // Guatemala
    '503' => 'SV', // El Salvador
    '504' => 'HN', // Honduras
    '505' => 'NI', // Nicaragua
    '507' => 'PA', // Panamá
    '598' => 'UY', // Uruguay
    '595' => 'PY', // Paraguay
    '591' => 'BO', // Bolivia
    '53' => 'CU',  // Cuba
    '1809' => 'DO', // República Dominicana
    '1829' => 'DO', // República Dominicana
    '1849' => 'DO', // República Dominicana
],
```

---

```php
// Primero estableces la cuenta con forAccount()
Whatsapp::account()->forAccount('1243432234423');


$account = Whatsapp::account()->register([
    'api_token' => 'EAAKt6D2DgZCMBPhMmgtjmnhvUa8O7rZA5zxWxU8UXso07zgugZAJwScJOd3KwHAOZAcnSdSi8wjZCPvVd33vk0ikI8kZBbxvjBN4nP7j5BF1dJiqHCQH9ER1kRFZClpiAOcGasebw8S08yDvwCarUSZCr6YJxojUgZDZD',
    'business_id' => '747336830188'
]);

// Primero establece la cuenta
// Establecer cuenta primero (IMPORTANTE)
Whatsapp::account()->forAccount('747336830188');

// Suscribir aplicación (usa campos por defecto de configuración)
$response = Whatsapp::account()->subscribeApp();

// Suscribir con campos específicos
$response = Whatsapp::account()->subscribeApp([
    'messages',
    'message_deliveries', 
    'message_reads',
    'message_template_status_update'
]);

// Obtener aplicaciones suscritas
$subscribedApps = Whatsapp::account()->subscribedApps();

// Cancelar suscripción
$response = Whatsapp::account()->unsubscribeApp();
```

---

## 9. Perfil de Empresa

El perfil de empresa del número de teléfono proporciona información adicional visible en WhatsApp: descripción, dirección, sitio web, etc.

### Obtener el perfil de empresa

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

Whatsapp::account()->forAccount('WABA_ID');

$profile = Whatsapp::account()->getBusinessProfile('API_PHONE_NUMBER_ID');

// Respuesta ejemplo:
// [
//   "about"               => "Especialistas en suculentas",
//   "address"             => "Calle 123, Bogotá",
//   "description"         => "Vendemos plantas desde 2010...",
//   "email"               => "info@empresa.com",
//   "profile_picture_url" => "https://pps.whatsapp.net/...",
//   "websites"            => ["https://empresa.com"],
//   "vertical"            => "RETAIL",
// ]
```

### Actualizar el perfil de empresa

```php
Whatsapp::account()->forAccount('WABA_ID');

$response = Whatsapp::account()->updateBusinessProfile('API_PHONE_NUMBER_ID', [
    'about'       => 'Especialistas en suculentas desde 2010.',
    'address'     => 'Calle 123 #45-67, Bogotá, Colombia',
    'description' => 'Ofrecemos una amplia variedad de plantas suculentas.',
    'email'       => 'info@empresa.com',
    'vertical'    => 'RETAIL',
    'websites'    => ['https://empresa.com'],
    // 'profile_picture_handle' => 'handle_obtenido_de_upload_session',
]);

// { "success": true }
```

> Los campos escalares (`about`, `address`, `description`, `email`, `vertical`) se sincronizan automáticamente en la base de datos local tras un POST exitoso.
> Para actualizar la foto de perfil, primero debés subir la imagen a través de una sesión de carga de medios y usar el `profile_picture_handle` resultante.

**Valores válidos para `vertical`:**

| Valor | Descripción |
|-------|-------------|
| `RETAIL` | Comercio minorista |
| `ENTERTAINMENT` | Entretenimiento |
| `EDUCATION` | Educación |
| `BEAUTY_SPA_SALON` | Belleza y spa |
| `HEALTH_AND_BEAUTY` | Salud y belleza |
| `OTHER` | Otro |

---

## 10. Nombre Visible

El nombre visible es el que aparece en el perfil de WhatsApp del número de teléfono. Todo cambio pasa por un proceso de revisión de Meta.

### Obtener el nombre visible actual

```php
Whatsapp::account()->forAccount('WABA_ID');

$status = Whatsapp::account()->getPhoneNumberNameStatus('API_PHONE_NUMBER_ID');

// {
//   "verified_name": "Mi Empresa",
//   "name_status":   "APPROVED",
//   "id":            "106540352242922"
// }
```

> `verified_name` es el nombre actualmente aprobado. `name_status` indica su estado de aprobación.

### Solicitar un cambio de nombre visible

```php
Whatsapp::account()->forAccount('WABA_ID');

$response = Whatsapp::account()->updateDisplayName(
    'API_PHONE_NUMBER_ID',
    'Mi Empresa Renovada'
);

// { "success": true }
```

Tras un POST exitoso, el paquete persiste automáticamente en la BD:
- `new_display_name` → el nombre solicitado
- `new_name_status` → `PENDING_REVIEW`

Cuando Meta aprueba o rechaza el nombre, llega el webhook `phone_number_name_update` que actualiza `verified_name` y `name_status` (ya manejado automáticamente por el paquete).

### Consultar el estado del nombre en revisión

```php
Whatsapp::account()->forAccount('WABA_ID');

$pending = Whatsapp::account()->getDisplayNamePendingStatus('API_PHONE_NUMBER_ID');

// {
//   "new_display_name": "Mi Empresa Renovada",
//   "new_name_status":  "PENDING_REVIEW",
//   "id":               "106540352242922"
// }
```

**Valores posibles de `new_name_status`:**

| Valor | Descripción |
|-------|-------------|
| `PENDING_REVIEW` | En revisión por Meta |
| `APPROVED` | Aprobado (pasará a `verified_name`) |
| `DECLINED` | Rechazado — revisar normas de nomenclatura |
| `AVAILABLE_WITHOUT_REVIEW` | Aprobado sin revisión |
| `EXPIRED` | La solicitud venció sin respuesta |

---

## 11. Cuenta de Empresa Oficial (OBA)

Una Cuenta de Empresa Oficial (OBA) es un número verificado como marca auténtica y relevante. Aparece con una marca de verificación azul en WhatsApp.

> **Requisitos previos:**
> - La empresa debe llevar al menos 30 días en la plataforma.
> - La verificación del negocio en Meta debe estar completa.
> - El número debe tener verificación en dos pasos activa.
> - El nombre visible debe estar aprobado.

### Solicitar estado OBA

```php
Whatsapp::account()->forAccount('WABA_ID');

$response = Whatsapp::account()->requestOfficialBusinessAccount(
    'API_PHONE_NUMBER_ID',
    [
        'business_website_url'              => 'https://www.miempresa.com',
        'parent_business_or_brand'          => 'Mi Empresa S.A.S.',
        'primary_country_of_operation'      => 'Colombia',
        'primary_language'                  => 'Spanish',
        'additional_supporting_information' => 'También somos mencionados en Revista Dinero y El Espectador.',
        'supporting_links' => [
            'https://www.revistadiero.com/2025/mi-empresa',
            'https://www.elespectador.com/2025/mi-empresa',
            'https://www.portafolio.co/2025/mi-empresa',
        ],
    ]
);

// { "success": true }
// Nota: true indica que la solicitud se envió, NO que fue aprobada.
```

Tras un envío exitoso, el paquete registra `oba_status = 'PENDING'` en la BD local.

### Consultar el estado de la solicitud OBA

```php
Whatsapp::account()->forAccount('WABA_ID');

$status = Whatsapp::account()->getOfficialBusinessAccountStatus('API_PHONE_NUMBER_ID');

// {
//   "official_business_account": {
//     "oba_status": "NOT_STARTED"
//   },
//   "is_official_business_account": false,
//   "id": "106540352242922"
// }
```

El paquete sincroniza automáticamente `oba_status` e `is_official` en la BD.

**Valores posibles de `oba_status`:**

| Valor | Descripción |
|-------|-------------|
| `NOT_STARTED` | Sin solicitud enviada |
| `PENDING` | Solicitud enviada, en revisión |
| `APPROVED` | Aprobada — el número tiene marca azul |
| `REJECTED` | Rechazada — podés reintentar en 30 días |

> Si la solicitud es rechazada, el paquete no dispara ningún webhook automáticamente — Meta notifica por Business Suite. Se recomienda consultar periódicamente con `getOfficialBusinessAccountStatus()`.

---

<div align="center">
  <table>
    <tr>
      <td align="left">
        <a href="01-instalacion.md" title="Sección anterior: Instalacion">◄◄ Instalacion</a>
      </td>
      <td align="center">
        <a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
      </td>
      <td align="right">
        <a href="03-mensajes.md" title="Sección siguiente: Envío de Mensajes">Gestión de Mensajes ►►</a>
      </td>
    </tr>
  </table>
</div>

<div align="center">
  <sub>Documentación del Webhook de WhatsApp Manager | 
    <a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a>
  </sub>
</div>

---

## ❤️ Apoyo

Si este proyecto te resulta útil, considera apoyar su desarrollo:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donar%20con-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

## 📄 Licencia

MIT License - Ver [LICENSE](LICENSE) para más detalles
