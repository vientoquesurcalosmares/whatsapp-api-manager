<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="06-webhook.md" title="Sección anterior: Webhook">◄◄ Webhook</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
    <td align="right">
      <a href="08-ejemplos.md" title="Sección siguiente">Ejemplos ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentación de WhatsApp Flows | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---

## 🌊 Gestión de WhatsApp Flows

### Introducción a WhatsApp Flows
WhatsApp Flows te permite crear experiencias ricas e interactivas directamente dentro de la aplicación de WhatsApp. Con este módulo, puedes diseñar formularios, procesos de reserva, encuestas y más, utilizando una interfaz fluida (Builder Pattern) en PHP, sin tener que lidiar manualmente con la complejidad de la estructura JSON que exige Meta.

**Características principales:**
- Sincronización bidireccional de flujos existentes.
- Construcción fluida de Pantallas (Screens) y Elementos (Elements).
- Edición dinámica de flujos en estado DRAFT.
- Publicación directa a la API de WhatsApp.
- Configuración automática de Endpoints para manejo de datos.

### 📚 Tabla de Contenidos
1. Sincronización de Flujos
2. Crear un Nuevo Flujo (Flow Builder)
3. Estructura de Pantallas y Elementos
4. Editar un Flujo Existente (Flow Editor)
5. Publicar un Flujo

---

## 1. Sincronización de Flujos

Antes de empezar a trabajar o si has creado flujos directamente desde el Business Manager de Meta, es recomendable sincronizarlos con tu base de datos local.

- **Sincronizar todos los flujos de la cuenta:**
    ```php
    use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
    use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

    $account = WhatsappBusinessAccount::first();

    // Esto descargará todos los flujos y su estructura JSON a la base de datos
    $flows = Whatsapp::flow()->syncFlows($account);
    ```

- **Sincronizar un flujo específico por su ID:**
    ```php
    // Útil si solo necesitas actualizar un flujo en particular
    $flowId = '1234567890';
    $flow = Whatsapp::flow()->syncFlowById($account, $flowId);
    ```

---

## 2. Crear un Nuevo Flujo (Flow Builder)

El paquete incluye un potente `FlowBuilder` que te permite diseñar la estructura visual del flujo paso a paso.

### Ejemplo: Flujo de Generación de Leads
Este ejemplo crea un flujo de captación de datos con una sola pantalla que pide nombre y correo.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

$account = WhatsappBusinessAccount::first();

// 1. Inicializar el constructor
$builder = Whatsapp::flow()->builder($account);

// 2. Definir metadatos y estructura
$flow = $builder
    ->name('Captura de Leads') // Máximo 120 caracteres
    ->type('MARKETING')        // AUTHENTICATION, MARKETING, UTILITY, SERVICE
    ->category('LEAD_GENERATION') 
    
    // 3. Construir la pantalla inicial
    ->screen('CONTACT_INFO')
        ->title('Déjanos tus datos')
        ->isStart(true) // Indica que es la primera pantalla
        
        // Agregar campo de nombre
        ->element('nombre')
            ->type('input')
            ->label('Nombre Completo')
            ->placeholder('Ej. Juan Pérez')
            ->required(true)
        ->endElement()
        
        // Agregar campo de correo
        ->element('email')
            ->type('input')
            ->label('Correo Electrónico')
            ->required(true)
        ->endElement()
        
        // Agregar botón de envío
        ->element('submit_btn')
            ->type('button')
            ->label('Enviar Datos')
            ->action('complete') // Acción para terminar el flujo
        ->endElement()
        
    ->endScreen() // Cierra la pantalla
    
    // 4. Guardar y subir a Meta
    ->save();

```


💡 Nota: Al ejecutar ->save(), el paquete automáticamente crea el flujo en Meta, sube el archivo JSON encriptado con la estructura, configura tu endpoint_uri y guarda el registro en tu base de datos con estado DRAFT.

3. Tipos de Elementos SoportadosAl construir tus pantallas con ->element('nombre_elemento'), puedes asignar diferentes tipos usando el método ->type():TipoDescripciónMétodos de configuracióninputCampo de texto libre.->label(), ->placeholder(), ->required()dropdownLista de selección.->label(), ->options([['label'=>'A', 'value'=>'a']])checkboxCasilla de verificación.->label(), ->required()buttonBotón de acción.->label(), ->action('nombre_accion', ['payload' => 'data'])


Ejemplo de Dropdown (Menú desplegable)


->element('pais')
    ->type('dropdown')
    ->label('Selecciona tu país')
    ->options([
        ['label' => 'Colombia', 'value' => 'CO'],
        ['label' => 'México', 'value' => 'MX'],
        ['label' => 'España', 'value' => 'ES']
    ])
->endElement()


4. Editar un Flujo Existente (Flow Editor)
Si el flujo aún está en estado DRAFT (Borrador), puedes modificarlo fácilmente utilizando el FlowEditor. Este editor te permite agregar, modificar o eliminar pantallas y elementos sin tener que reescribir todo el flujo.

use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// 1. Obtener el flujo de la base de datos
$flow = Whatsapp::flow()->getFlowById('wa_flow_12345');

// 2. Iniciar el editor
$editor = Whatsapp::flow()->edit($flow);

// 3. Modificar el flujo
$editor
    ->name('Captura de Leads (Actualizado)')
    ->description('Nueva versión del formulario')
    
    // Modificar un elemento existente en una pantalla
    ->screen('CONTACT_INFO')
        ->title('Actualiza tus datos') // Cambia el título de la pantalla
        
        // Agregar un elemento nuevo
        ->element('telefono')
            ->type('input')
            ->label('Número de Teléfono')
            ->required(false)
        ->endElement()
    ->endScreen()
    
    // Guardar cambios en Meta
    ->save();


Operaciones Avanzadas del Editor
Eliminar un elemento: ->removeElement('email')

Eliminar una pantalla completa: ->removeScreen('CONTACT_INFO')

Reordenar pantallas: ->reorderScreens(['SCREEN_2', 'SCREEN_1'])

5. Publicar un Flujo
Una vez que hayas terminado de diseñar y probar tu flujo, debes publicarlo para que los usuarios puedan interactuar con él.

⚠️ Advertencia: Una vez que un flujo cambia de estado DRAFT a PUBLISHED, su estructura JSON no puede volver a ser modificada. Si necesitas hacer cambios, deberás clonarlo o crear uno nuevo.




EJEMPLO CON JSON

<div align="center">
<table>
  <tr>
    <td align="left">
      <a href="07-flows.md" title="Sección anterior: Flows">◄◄ WhatsApp Flows</a>
    </td>
    <td align="center">
      <a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
    </td>
    <td align="right">
      <a href="09-soporte.md" title="Sección siguiente">Soporte ►►</a>
    </td>
  </tr>
</table>
</div>

<div align="center">
<sub>Documentación de WhatsApp Flows | 
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

---

## 🛒 Ejemplos Avanzados: Flujo de Checkout con Lógica Condicional

El `FlowBuilder` de nuestro paquete está diseñado para soportar la creación de interfaces complejas utilizando métodos encadenados (Fluent Interface). Esto incluye el uso de variables locales (`data`), componentes avanzados (`RadioButtonsGroup`), navegación entre pantallas con paso de parámetros (`payload`) y renderizado condicional (`If`).

### Caso de Uso: Proceso de Compra (Checkout)
Este flujo consta de dos pantallas:
1. **Selección:** El usuario elige un producto de una lista dinámica y escribe su dirección.
2. **Checkout:** Se muestra un resumen, se elige el método de pago y, **solo si** selecciona "Transferencia Bancaria", se despliegan campos adicionales para seleccionar el banco y anotar el número de comprobante.

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

$account = WhatsappBusinessAccount::first();

// 1. Inicializar el constructor
$builder = Whatsapp::flow()->builder($account)
    ->name('Checkout Avanzado')
    ->type('UTILITY')
    ->category('SHOPPING');

// ---------------------------------------------------------
// PANTALLA 1: SELECCIÓN DE PRODUCTO
// ---------------------------------------------------------
$builder->screen('PRODUCT_SELECTION')
    ->title('Selecciona Producto')
    
    // Definimos los datos locales (Variables) para esta pantalla
    ->data([
        'product_list' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string']
                ]
            ],
            '__example__' => [
                ['id' => 'p1', 'title' => 'Suscripción SaaS - $29.99'],
                ['id' => 'p2', 'title' => 'Setup Inicial - $99.00']
            ]
        ]
    ])
    
    // Título interno de la vista
    ->element('heading')
        ->type('TextHeading')
        ->text('Tu Pedido')
    ->endElement()
    
    // Desplegable alimentado por la variable de datos
    ->element('product_id')
        ->type('dropdown')
        ->label('Elige un producto')
        ->required(true)
        ->dataSource('${data.product_list}')
    ->endElement()
    
    // Campo de texto normal
    ->element('shipping_addr')
        ->type('input')
        ->label('Dirección de envío')
        ->required(true)
    ->endElement()
    
    // Botón de navegación a la siguiente pantalla pasando el Payload
    ->element('footer_btn')
        ->type('button')
        ->label('Revisar Pedido')
        ->action('navigate', [
            'bank_list' => [
                ['id' => 'bancolombia', 'title' => 'Bancolombia'],
                ['id' => 'nequi', 'title' => 'Nequi']
            ],
            'payment_methods' => [
                ['id' => 'transfer', 'title' => 'Transferencia Bancaria'],
                ['id' => 'card', 'title' => 'Tarjeta (Stripe)']
            ]
        ])
        ->nextScreen('CHECKOUT_SCREEN') // Define a qué pantalla ir
    ->endElement()
->endScreen();


// ---------------------------------------------------------
// PANTALLA 2: CHECKOUT Y CONDICIONALES
// ---------------------------------------------------------
$builder->screen('CHECKOUT_SCREEN')
    ->title('Confirmación y Pago')
    ->terminal(true) // Indica que es una pantalla final
    ->success(true)  // Aplica estilos de éxito
    
    ->data([
        'payment_methods' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string']
                ]
            ]
        ],
        'bank_list' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string']
                ]
            ]
        ]
    ])
    
    ->element('resumen_heading')
        ->type('TextSubheading')
        ->text('Resumen de compra')
    ->endElement()
    
    // Imprimir variables de la pantalla anterior
    ->element('resumen_body')
        ->type('TextBody')
        ->text("`'Producto: ' \${screen.PRODUCT_SELECTION.form.product_id}`")
    ->endElement()
    
    // Grupo de botones de radio
    ->element('method')
        ->type('RadioButtonsGroup')
        ->label('Método de Pago')
        ->required(true)
        ->dataSource('${data.payment_methods}')
    ->endElement()
    
    // LÓGICA CONDICIONAL: Solo se muestra si eligen transferencia
    ->element('condicional_banco')
        ->type('If')
        ->condition("\${form.method} == 'transfer'")
        ->then([
            [
                'type' => 'TextCaption',
                'text' => 'Selecciona el banco y escribe el número de comprobante:'
            ],
            [
                'type' => 'Dropdown',
                'label' => 'Banco',
                'name' => 'selected_bank',
                'required' => true,
                'data-source' => '${data.bank_list}'
            ],
            [
                'type' => 'TextInput',
                'label' => 'Número de Comprobante',
                'name' => 'ref_number',
                'input-type' => 'number',
                'required' => true
            ]
        ])
    ->endElement()
    
    // Botón final que recolecta la información de AMBAS pantallas
    ->element('submit_btn')
        ->type('button')
        ->label('Finalizar Pedido')
        ->action('complete', [
            'p_id' => '${screen.PRODUCT_SELECTION.form.product_id}',
            'addr' => '${screen.PRODUCT_SELECTION.form.shipping_addr}',
            'pay_method' => '${form.method}',
            'bank' => '${form.selected_bank}',
            'reference' => '${form.ref_number}'
        ])
    ->endElement()
->endScreen();

// 3. Guardar y publicar el flujo en Meta
$flow = $builder->save();

echo "Flujo de Checkout creado con éxito. ID: " . $flow->wa_flow_id;

```



## 🚀 Ejemplos Avanzados de WhatsApp Flows

### Flujo de Checkout con Lógica Condicional
Cuando necesitas crear flujos altamente complejos que involucran **variables dinámicas, renderizado condicional (`If`), objetos de datos locales (`data`) y múltiples pantallas con banderas de terminal**, la forma más eficiente de utilizar el paquete es inyectar la estructura cruda (Raw Array) usando el método `setJsonStructure()`.

Este enfoque te da acceso absoluto al 100% de las capacidades de Meta, manteniendo la comodidad de guardado y publicación de nuestro paquete.

#### Ejemplo: Proceso de Compra (Checkout)
Este flujo consta de dos pantallas. La primera permite seleccionar un producto y agregar una dirección. La segunda pantalla confirma el pedido, pregunta el método de pago y, condicionalmente, despliega campos adicionales si el usuario elige "Transferencia Bancaria".

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

$account = WhatsappBusinessAccount::first();

// Estructura avanzada del Flujo en formato Array PHP
$advancedStructure = [
    'version' => '7.3',
    'screens' => [
        [
            'id' => 'PRODUCT_SELECTION',
            'title' => 'Selecciona Producto',
            'data' => [
                'product_list' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string']
                        ]
                    ],
                    '__example__' => [
                        ['id' => 'p1', 'title' => 'Suscripción SaaS - $29.99'],
                        ['id' => 'p2', 'title' => 'Setup Inicial - $99.00']
                    ]
                ]
            ],
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    ['type' => 'TextHeading', 'text' => 'Tu Pedido'],
                    [
                        'type' => 'Dropdown',
                        'label' => 'Elige un producto',
                        'name' => 'product_id',
                        'required' => true,
                        'data-source' => '${data.product_list}'
                    ],
                    [
                        'type' => 'TextInput',
                        'label' => 'Dirección de envío',
                        'name' => 'shipping_addr',
                        'required' => true
                    ],
                    [
                        'type' => 'Footer',
                        'label' => 'Revisar Pedido',
                        'on-click-action' => [
                            'name' => 'navigate',
                            'next' => ['type' => 'screen', 'name' => 'CHECKOUT_SCREEN'],
                            'payload' => [
                                'bank_list' => [
                                    ['id' => 'bancolombia', 'title' => 'Bancolombia'],
                                    ['id' => 'nequi', 'title' => 'Nequi']
                                ],
                                'payment_methods' => [
                                    ['id' => 'transfer', 'title' => 'Transferencia Bancaria'],
                                    ['id' => 'card', 'title' => 'Tarjeta (Stripe)']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        [
            'id' => 'CHECKOUT_SCREEN',
            'title' => 'Confirmación y Pago',
            'terminal' => true,
            'success' => true,
            'data' => [
                'payment_methods' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string']
                        ]
                    ]
                ],
                'bank_list' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string']
                        ]
                    ]
                ]
            ],
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    ['type' => 'TextSubheading', 'text' => 'Resumen de compra'],
                    [
                        'type' => 'TextBody',
                        'text' => "`'Producto: ' \${screen.PRODUCT_SELECTION.form.product_id}`"
                    ],
                    [
                        'type' => 'RadioButtonsGroup',
                        'label' => 'Método de Pago',
                        'name' => 'method',
                        'required' => true,
                        'data-source' => '${data.payment_methods}'
                    ],
                    [
                        'type' => 'If',
                        'condition' => "\${form.method} == 'transfer'",
                        'then' => [
                            [
                                'type' => 'TextCaption',
                                'text' => 'Selecciona el banco y escribe el número de comprobante:'
                            ],
                            [
                                'type' => 'Dropdown',
                                'label' => 'Banco',
                                'name' => 'selected_bank',
                                'required' => true,
                                'data-source' => '${data.bank_list}'
                            ],
                            [
                                'type' => 'TextInput',
                                'label' => 'Número de Comprobante',
                                'name' => 'ref_number',
                                'input-type' => 'number',
                                'required' => true
                            ]
                        ]
                    ],
                    [
                        'type' => 'Footer',
                        'label' => 'Finalizar Pedido',
                        'on-click-action' => [
                            'name' => 'complete',
                            'payload' => [
                                'p_id' => '${screen.PRODUCT_SELECTION.form.product_id}',
                                'addr' => '${screen.PRODUCT_SELECTION.form.shipping_addr}',
                                'pay_method' => '${form.method}',
                                'bank' => '${form.selected_bank}',
                                'reference' => '${form.ref_number}'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
];

// Construir y guardar el flujo usando la estructura inyectada
$flow = Whatsapp::flow()->builder($account)
    ->name('Flujo de Pago Avanzado')
    ->type('UTILITY')
    ->category('SHOPPING')
    ->setJsonStructure($advancedStructure) // Inyectamos la estructura completa
    ->save();

echo "Flujo creado con ID: " . $flow->wa_flow_id;





use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

// Obtener el flujo
$flow = Whatsapp::flow()->getFlowById('wa_flow_12345');

// Publicar el flujo en la API de Meta
try {
    Whatsapp::flow()->publish($flow);
    echo "¡Flujo publicado con éxito!";
} catch (\Exception $e) {
    echo "Error al publicar: " . $e->getMessage();
}



El método publish() cambiará el estado en Meta, actualizará tu base de datos local y sincronizará el estado de salud y validación final del flujo.


<div align="center">
<table>
<tr>
<td align="left">
<a href="06-webhook.md" title="Sección anterior: Webhook">◄◄ Webhook</a>
</td>
<td align="center">
<a href="00-tabla-de-contenido.md" title="Tabla de contenido">▲ Tabla de contenido</a>
</td>
<td align="right">
<a href="08-ejemplos.md" title="Sección siguiente">Ejemplos ►►</a>
</td>
</tr>
</table>
</div>

<div align="center">
<sub>Documentación de WhatsApp Flows |
<a href="https://github.com/djdang3r/whatsapp-api-manager">Ver en GitHub</a></sub>
</div>

❤️ Apoyo
Si este proyecto te resulta útil, considera apoyar su desarrollo:

📄 Licencia
MIT License - Ver LICENSE para más detalles