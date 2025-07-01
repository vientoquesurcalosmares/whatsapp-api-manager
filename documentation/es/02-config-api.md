
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
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---

## 🚀 🧩 Configuración de API

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

  >⚠️ Importante:
  >Asegurece de configurar las variables en el .env

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

  $account = Whatsapp::account()->register([
      'api_token' => '***********************',
      'business_id' => '1243432234423'
  ]);
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

4. Vinculación con Contactos
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
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---

## ❤️ Apoyo

Si este proyecto te resulta útil, considera apoyar su desarrollo:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donar%20con-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

## 📄 Licencia

MIT License - Ver [LICENSE](LICENSE) para más detalles