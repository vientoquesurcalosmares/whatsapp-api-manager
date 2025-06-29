
---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="03-mensajes.md" title="Sección anterior">◄◄ Gestion de Mensajes</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
    <td align="right">
      <a href="05-eventos.md" title="Sección siguiente">Eventos ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentación del Webhook de WhatsApp Manager | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---

## 📋 Gestión de Plantillas

### Introducción
El módulo de plantillas proporciona herramientas completas para crear, administrar y enviar mensajes basados en plantillas aprobadas por WhatsApp. Las plantillas son esenciales para comunicaciones automatizadas como notificaciones, promociones y mensajes transaccionales, permitiéndote mantener consistencia en tu comunicación mientras cumples con las políticas de WhatsApp.


**Características principales:**
- Creación de plantillas para diferentes categorías (utilidad, marketing)
- Gestión de versiones y componentes (cabeceras, cuerpos, pies de página, botones)
- Sincronización con la API de WhatsApp
- Envío masivo de mensajes basados en plantillas
- Edición avanzada con validación en tiempo real


### 📚 Tabla de Contenidos
1. Administracion de Plantillas
    - Obtener odas las plantillas
    - Obtener Por nombre
    - Obtener Por ID
    - Eliminar Plantillas
    - Soft delete
    - Hard delete

1. Editar Plantillas
    - Gestión de componentes
    - Validaciones
    - Manejo de errores

2. Crear Plantillas
    - Plantillas de utilidad
    - Plantillas de marketing
    - Con imágenes
    - Con botones
    - Variaciones

3. Enviar Mensajes con Plantillas






## Administracion de Plantillas

- **Obtener todas las plantillas de una cuenta de whatsapp**
    Se obtienen todas las plantillas de una cuenta de whatsapp y se almacenan en la base de datos.
    Se hace la peticion a la API de whatsapp para obtener todas las plantillas que estan asociadas a la cuenta de whatsapp.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Obtener una instancia de WhatsApp Business Account
    $account = WhatsappBusinessAccount::find($accountId);

    // Obtener todas las plantillas de la cuenta
    Whatsapp::template()->getTemplates($account);
    ```

- **Obtener una plantilla por el nombre.**
    Se hace la peticion a la API de whatsapp para obtener una plantilla por el nombre y se almacena en la base de datos.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Obtener una instancia de WhatsApp Business Account
    $account = WhatsappBusinessAccount::find($accountId);

    // Obtener plantilla por su nombre
    $template = Whatsapp::template()->getTemplateByName($account, 'order_confirmation');
    ```


- **Obtener una plantilla por el ID.**
    Se hace la peticion a la API de whatsapp para obtener una plantilla por el ID y se almacena en la base de datos.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Obtener una instancia de WhatsApp Business Account
    $account = WhatsappBusinessAccount::find($accountId);

    // Obtener plantilla por su ID
    $template = Whatsapp::template()->getTemplateById($account, '559947779843204');
    ```

- **Eliminar plantilla de la API y de la base de datos al mismo tiempo.**
    Se hace la peticion a la API de whatsapp para obtener una plantilla por el ID y se elimina la plantilla seleccionada, Existen dos maneras de eliminar Soft Delete y Hard Delete.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Obtener una instancia de WhatsApp Business Account
    $account = WhatsappBusinessAccount::find($accountId);

    // Soft delete
    // Eliminar plantilla por su ID
    $template = Whatsapp::template()->gdeleteTemplateById($account, $templateId);

    // Eliminar plantilla por su Nombre
    $template = Whatsapp::template()->deleteTemplateByName($account, 'order_confirmation');


    // Hard delete
    // Eliminar plantilla por su ID
    $template = Whatsapp::template()->gdeleteTemplateById($account, $templateId, true);

    // Eliminar plantilla por su Nombre
    $template = Whatsapp::template()->deleteTemplateByName($account, 'order_confirmation', true);
    ```




- **Editar plantilla de la API y de la base de datos al mismo tiempo.**
    Se hace la peticion a la API de whatsapp para editar la plantilla seleccionada.

    ```php
    use ScriptDevelop\WhatsappManager\Models\Template;
    use ScriptDevelop\WhatsappManager\Exceptions\TemplateComponentException;
    use ScriptDevelop\WhatsappManager\Exceptions\TemplateUpdateException;

    $template = Template::find('template-id');

    try {
        $updatedTemplate = $template->edit()
            ->setName('nuevo-nombre-plantilla')
            ->changeBody('Nuevo contenido del cuerpo {{1}}', [['Ejemplo nuevo']])
            ->removeHeader()
            ->addFooter('Nuevo texto de pie de página')
            ->removeAllButtons()
            ->addButton('URL', 'Visitar sitio', 'https://mpago.li/2qe5G7E')
            ->addButton('QUICK_REPLY', 'Confirmar')
            ->update();
        
        return response()->json($updatedTemplate);
        
    } catch (TemplateComponentException $e) {
        // Manejar error de componente
        return response()->json(['error' => $e->getMessage()], 400);
        
    } catch (TemplateUpdateException $e) {
        // Manejar error de actualización
        return response()->json(['error' => $e->getMessage()], 500);
    }
    ```

    **Agregar componentes a plantillas que no lo tenian:**

    ```php
    $template->edit()
        ->addHeader('TEXT', 'Encabezado agregado')
        ->addFooter('Pie de página nuevo')
        ->addButton('PHONE_NUMBER', 'Llamar', '+1234567890')
        ->update();
    ```

    **Eliminar componentes existentes:**
    
    ```php
    $template->edit()
        ->removeFooter()
        ->removeAllButtons()
        ->update();
    ```

    **Trabajar con componentes específicos:**
    
    ```php
    $editor = $template->edit();

    // Verificar y modificar header
    if ($editor->hasHeader()) {
        $headerData = $editor->getHeader();
        if ($headerData['format'] === 'TEXT') {
            $editor->changeHeader('TEXT', 'Encabezado actualizado');
        }
    } else {
        $editor->addHeader('TEXT', 'Nuevo encabezado');
    }

    // Modificar botones
    $buttons = $editor->getButtons();
    foreach ($buttons as $index => $button) {
        if ($button['type'] === 'URL' && str_contains($button['url'], 'old-domain.com')) {
            $newUrl = str_replace('old-domain.com', 'new-domain.com', $button['url']);
            $editor->removeButtonAt($index);
            $editor->addButton('URL', $button['text'], $newUrl);
        }
    }

    $editor->update();
    ```

## Características Clave del Edit Template

    1.- Gestión completa de componentes:
        - Métodos add, change, remove para cada tipo de componente
        - Métodos has para verificar existencia
        - Métodos get para obtener datos

    2.- Validaciones robustas:
        - Unicidad de componentes (solo un HEADER, BODY, etc.)
        - Componentes obligatorios (BODY siempre requerido)
        - Límites de botones (máximo 10)
        - Restricciones de modificación (no cambiar categoría, no modificar aprobadas)

    3.- Operaciones atómicas:
        - removeButtonAt: Elimina un botón específico
        - removeAllButtons: Elimina todos los botones
        - getButtons: Obtiene todos los botones actuales

    4.- Manejo de errores:
        - Excepciones específicas para problemas de componentes
        - Excepciones para fallos en la actualización
        - Mensajes de error claros y descriptivos

    5.- Flujo intuitivo:
        - $template->edit() inicia la edición
        - Encadenamiento de métodos para modificaciones
        - update() aplica los cambios

## ❤️Apóyanos con una donación en GitHub Sponsors

Me puedes apoyar como desarrollador open source en GitHub Sponsors:
- Si este proyecto te ha sido útil, puedes apoyarlo con una donación a través de
[![Sponsor](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)

- O tambien por Mercadopago Colombia.
[![Donar con Mercado Pago](https://img.shields.io/badge/Donar%20con-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)
Gracias por tu apoyo 💙
---

## Crear las plantillas en una cuenta de whatsapp
- ### Crear Plantillas de Utilidad

    Las plantillas transaccionales son ideales para notificaciones como confirmaciones de pedidos, actualizaciones de envío, etc.

    ![Ejemplo de plantilla de marketing](../../assets/template_1.png "Plantilla de Marketing")

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Obtener la cuenta empresarial
    $account = WhatsappBusinessAccount::first();

    // Crear una plantilla transaccional
    $template = Whatsapp::template()
        ->createUtilityTemplate($account)
        ->setName('order_confirmation_3')
        ->setLanguage('en_US')
        ->addHeader('TEXT', 'Order Confirmation')
        ->addBody('Your order {{1}} has been confirmed.', ['12345'])
        ->addFooter('Thank you for shopping with us!')
        ->addButton('QUICK_REPLY', 'Track Order')
        ->addButton('QUICK_REPLY', 'Contact Support')
        ->save();
    ```
---

  - ### Crear Plantillas de Marketing

    Las plantillas de marketing son útiles para promociones, descuentos y campañas masivas.

    ![Ejemplo de plantilla de marketing](../../assets/template_2.png "Plantilla de Marketing")

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Obtener la cuenta empresarial
    $account = WhatsappBusinessAccount::first();

    // Crear una plantilla de marketing con texto
    $template = Whatsapp::template()
        ->createMarketingTemplate($account)
        ->setName('personal_promotion_text_only')
        ->setLanguage('en')
        ->addHeader('TEXT', 'Our {{1}} is on!', ['Summer Sale'])
        ->addBody(
            'Shop now through {{1}} and use code {{2}} to get {{3}} off of all merchandise.',
            ['the end of August', '25OFF', '25%']
        )
        ->addFooter('Use the buttons below to manage your marketing subscriptions')
        ->addButton('QUICK_REPLY', 'Unsubscribe from Promos')
        ->addButton('QUICK_REPLY', 'Unsubscribe from All')
        ->save();
    ```

---

  - ### Crear Plantillas de Marketing con Imágenes

    Las plantillas de marketing también pueden incluir imágenes en el encabezado para hacerlas más atractivas.

    ![Ejemplo de plantilla de marketing](../../assets/template_3.png "Plantilla de Marketing")

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Obtener la cuenta empresarial
    $account = WhatsappBusinessAccount::first();

    // Ruta de la imagen
    $imagePath = storage_path('app/public/laravel-whatsapp-manager.png');

    // Crear una plantilla de marketing con imagen
    $template = Whatsapp::template()
        ->createMarketingTemplate($account)
        ->setName('image_template_test')
        ->setLanguage('en_US')
        ->setCategory('MARKETING')
        ->addHeader('IMAGE', $imagePath)
        ->addBody('Hi {{1}}, your order {{2}} has been shipped!', ['John', '12345'])
        ->addFooter('Thank you for your purchase!')
        ->save();
    ```

---

- ### Crear Plantillas de Marketing con Botones de URL

    Puedes agregar botones de URL personalizados para redirigir a los usuarios a páginas específicas.

    ![Ejemplo de plantilla de marketing](../../assets/template_3.png "Plantilla de Marketing")

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    // Obtener la cuenta empresarial
    $account = WhatsappBusinessAccount::first();

    // Ruta de la imagen
    $imagePath = storage_path('app/public/laravel-whatsapp-manager.png');

    // Crear una plantilla de marketing con imagen y botones de URL
    $template = Whatsapp::template()
        ->createMarketingTemplate($account)
        ->setName('image_template_test_2')
        ->setLanguage('en_US')
        ->setCategory('MARKETING')
        ->addHeader('IMAGE', $imagePath)
        ->addBody('Hi {{1}}, your order {{2}} has been shipped!', ['John', '12345'])
        ->addFooter('Thank you for your purchase!')
        ->addButton('PHONE_NUMBER', 'Call Us', '+573234255686')
        ->addButton('URL', 'Track Order', 'https://mpago.li/{{1}}', ['2qe5G7E'])
        ->save();
    ```
---

- ### Crear Variaciones de Plantillas de Marketing

    Puedes crear múltiples variaciones de plantillas para diferentes propósitos.

    ![Ejemplo de plantilla de marketing](../../assets/template_4.png "Plantilla de Marketing")

    ```php
        use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
        use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

        // Obtener la cuenta empresarial
        $account = WhatsappBusinessAccount::first();

        // Crear una variación de plantilla de marketing
        $template = Whatsapp::template()
            ->createMarketingTemplate($account)
            ->setName('personal_promotion_text_only_22')
            ->setLanguage('en')
            ->addHeader('TEXT', 'Our {{1}} is on!', ['Summer Sale'])
            ->addBody(
                'Shop now through {{1}} and use code {{2}} to get {{3}} off of all merchandise.',
                ['the end of August', '25OFF', '25%']
            )
            ->addFooter('Use the buttons below to manage your marketing subscriptions')
            ->addButton('QUICK_REPLY', 'Unsubscribe from Promos')
            ->addButton('QUICK_REPLY', 'Unsubscribe from All')
            ->save();
    ```
    # Notas

    - Verifica que las imágenes usadas en las plantillas cumplan con los requisitos de la API de WhatsApp: formato (JPEG, PNG), tamaño máximo permitido y dimensiones recomendadas.
    - Los botones de tipo URL pueden aceptar parámetros dinámicos mediante variables de plantilla (`{{1}}`, `{{2}}`, etc.), lo que permite personalizar los enlaces para cada destinatario.
    - Si experimentas problemas al crear plantillas, consulta los archivos de log para obtener información detallada sobre posibles errores y su solución.


---
## ❤️Apóyanos con una donación en GitHub Sponsors

Me puedes apoyar como desarrollador open source en GitHub Sponsors:
- Si este proyecto te ha sido útil, puedes apoyarlo con una donación a través de
[![Sponsor](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)

- O tambien por Mercadopago Colombia.
[![Donar con Mercado Pago](https://img.shields.io/badge/Donar%20con-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)
Gracias por tu apoyo 💙
---

## Enviar Mensajes a partir de Plantilla creada.
  - ### Enviar mensajes de plantillas

    Puedes enviar diferentes mensajes de plantillas segun la estructura de la plantilla.

    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
    use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;

    // Obtener la cuenta empresarial
    $account = WhatsappBusinessAccount::first();
    $phone = WhatsappPhoneNumber::first();

    // Enviar plantilla 1
    $message = Whatsapp::template()
        ->sendTemplateMessage($phone)
        ->to('57', '3137555908')
        ->usingTemplate('order_confirmation_4')
        ->addBody(['12345'])
        ->send();

    // Enviar plantilla 2

    $message = Whatsapp::template()
        ->sendTemplateMessage($phone)
        ->to('57', '3135666627')
        ->usingTemplate('link_de_pago')
        ->addHeader('TEXT', '123456')
        ->addBody(['20000'])
        ->addButton('URL', 'Pagar', '1QFwRV', ['[https://mpago.li/1QFwRV]'])
        ->send();

    $message = Whatsapp::template()
        ->sendTemplateMessage($phone)
        ->to('57', '3135666627')
        ->usingTemplate('link_de_pago')
        ->addHeader('TEXT', '123456')
        ->addBody(['20000'])
        ->addButton(
            'URL', // Tipo de boton
            'Pagar', // Texto del boton
            '1QFwRV', // Variable del boton 'Solo Tipo URL'
            ['[https://mpago.li/1QFwRV]'] // URL de ejemplo 'No se envia solo se toma de ejemplo'
        )
        ->send();
    ```


---

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="03-mensajes.md" title="Sección anterior">◄◄ Gestion de Mensajes</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
    <td align="right">
      <a href="05-eventos.md" title="Sección siguiente">Eventos ►►</a>
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
