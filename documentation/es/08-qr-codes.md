# API y Configuración de Códigos QR

El ecosistema de WhatsApp Business permite a las empresas generar Códigos QR que, al escanearse, redirigen a un link de WhatsApp (wa.me) con un mensaje pre-cargado. 

El paquete `whatsapp-api-manager` expone un Facade especializado para despachar y sincronizar todos estos códigos en tu aplicación de manera fluída.

---

## Módulo de QrCodes (`Whatsapp::qrCode()`)

A través de la fachada principal `Whatsapp`, puedes acceder al gestor de códigos QR. Todos los métodos de API exigen que pases el modelo `WhatsappPhoneNumber` como referencia.

### 1. Crear un QR Code

Genera un QR en Graph API de META y lo almacena localmente en la base de datos `whatsapp_qr_codes`.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// El Phone Number ID (ULID) de la base de datos local
$phoneNumberId = 'tu_phone_number_id_ulid';
$prefilledMessage = '¡Hola! Me gustaría más información sobre el paquete premium.';

// Genera automáticamente el código en META y lo guarda en BD
$qrCode = Whatsapp::qrCode()->create($phoneNumberId, $prefilledMessage);

if ($qrCode) {
    echo "¡QR generado con éxito!";
    echo "Deep Link: " . $qrCode->deep_link_url;
    echo "Imagen SVG: " . $qrCode->qr_image_url;
} else {
    echo "Ha ocurrido un error al crear el QR.";
}
```

### 2. Recuperar y Sincronizar todos los QRs de un Número

Trae íntegramente la lista de códigos QR desde Meta, limpia tu tabla local de códigos eliminados remotamente, y genera los registros locales de QRs creados en la interfaz visual del Manager de WhatsApp.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Sincroniza y devuelve una colección (Collection de Eloquent)
// de modelos WhatsappQrCode
$allQrCodes = Whatsapp::qrCode()->syncAll($phoneNumberId);

if ($allQrCodes) {
    foreach ($allQrCodes as $qr) {
        echo $qr->code . ' - ' . $qr->prefilled_message;
    }
}
```

> **Nota:** Cada modelo de la colección incluye el campo `code` (hash único del QR), `prefilled_message`, `deep_link_url` y `qr_image_url`.

### 3. Obtener un QR Específico

Recupera manualmente el token y la imagen original de un QR específico por medio de su código.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$codigoHash = 'ANED2T5QRU7HG1';

// Formato opcional (SVG o PNG), por default SVG
$qr = Whatsapp::qrCode()->get($phoneNumberId, $codigoHash, 'PNG');

if ($qr) {
    echo "El prefilled message es: " . $qr->prefilled_message;
    echo "Puedes descargar el PNG en: " . $qr->qr_image_url;
}
```

> **Nota:** El modelo retornado incluye el campo `code` con el hash identificador del QR.
```

### 4. Actualizar el Mensaje de un QR Existente

Si quieres cambiar el texto precargado de un código QR ya existente, puedes usar el método de actualización:

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$codigoHash = 'ANED2T5QRU7HG1';
$nuevoMensaje = '¡Hola! Quiero activar mi suscripción navideña.';

$qrActualizado = Whatsapp::qrCode()->update($phoneNumberId, $codigoHash, $nuevoMensaje);

if ($qrActualizado) {
    echo "Actualizado! Nuevo modelo salvado localmente.";
}
```

### 5. Eliminar un Código QR

Destruye el código de Meta e inmediatamente lo da de baja en la tabla de tu base de datos:

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$codigoHash = 'ANED2T5QRU7HG1';

if (Whatsapp::qrCode()->delete($phoneNumberId, $codigoHash)) {
    echo "¡Código destruido exitosamente!";
} else {
    echo "Ocurrió un error (o ya estaba eliminado).";
}
```

### 6. Descarga Física Automática del QR

La descarga física del archivo (SVG o PNG) a tu almacenamiento local se realiza **automáticamente** cada vez que instancias un QR mediante los métodos `create()`, `syncAll()` o `get()`. El paquete entra a la URL expirable de Meta, extrae la imagen a tu disco `public` a través de Storage de forma automática, y anexa su ruta al modelo local bajo el atributo `qr_image_path`.

El formato del archivo guardado se detecta automáticamente inspeccionando el `Content-Type` de la respuesta de Meta (`image/svg+xml` → `.svg`, `image/png` → `.png`), sin depender del parámetro que el usuario haya solicitado.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$codigoHash = 'ANED2T5QRU7HG1';

// Forzar re-descarga manual sobreescribiendo la imagen física existente (Format puede ser 'SVG' o 'PNG')
$qrModel = Whatsapp::qrCode()->downloadImage($phoneNumberId, $codigoHash, 'PNG');

if ($qrModel && $qrModel->qr_image_path) {
    echo "Imagen recuperada y guardada en: " . Storage::url($qrModel->qr_image_path);
}
```

> **Nota importante sobre el almacenamiento:** Cuando eliminas un QR físicamente de Meta por medio de `Whatsapp::qrCode()->delete()`, el paquete automáticamente detecta y también borra el archivo físico SVG/PNG descargado previamente para que no arrastres basura en el servidor.
