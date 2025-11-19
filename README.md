[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/djdang3r/whatsapp-api-manager)

![WhatsApp Business API Manager](https://raw.githubusercontent.com/djdang3r/whatsapp-api-manager/main/assets/whatsapp-api-cloud.png "WhatsApp Business API Manager")

# WhatsApp Business API Manager for Laravel

**The most elegant way to integrate WhatsApp Business in Laravel**

<p align="center">
<a href="https://packagist.org/packages/scriptdevelop/whatsapp-manager"><img src="https://img.shields.io/packagist/v/scriptdevelop/whatsapp-manager.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://php.net/"><img src="https://img.shields.io/badge/PHP-8.2%2B-8892BF.svg?style=flat-square" alt="PHP Version"></a>
<a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-12%2B-FF2D20.svg?style=flat-square" alt="Laravel Version"></a>
<a href="https://packagist.org/packages/scriptdevelop/whatsapp-manager"><img src="https://img.shields.io/packagist/dt/scriptdevelop/whatsapp-manager" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/scriptdevelop/whatsapp-manager"><img src="https://img.shields.io/packagist/l/scriptdevelop/whatsapp-manager" alt="License"></a>
</p>

---

### 🌐 Language / Idioma

<a href="#english"><img src="https://flagcdn.com/us.svg" width="20"> 🇺🇸 English</a> | <a href="#español">🇪🇸 Español <img src="https://flagcdn.com/es.svg" width="20"></a>

---

## 🇺🇸 English

![WhatsApp Business API Manager](https://raw.githubusercontent.com/djdang3r/whatsapp-api-manager/main/assets/whatsapp-api-cloud.png "WhatsApp Business API Manager")

<div align="center">

### 📚 Complete Documentation Available!

<a href="https://laravelwhatsappmanager.com/docs/en">
  <img src="https://img.shields.io/badge/📖_View_Complete_Documentation-FF6B6B?style=for-the-badge&logo=bookstack&logoColor=white&labelColor=FF6B6B" alt="View Complete Documentation" height="50" />
</a>

**[👉 Click here to view the complete documentation](https://laravelwhatsappmanager.com/docs/en)**

</div>

# WhatsApp Business API Manager for Laravel

## 📖 Description

`scriptdevelop/whatsapp-manager` is a complete and elegant package designed to simplify the integration of WhatsApp Business API into your Laravel projects. It provides a fluid and expressive interface that feels natural in Laravel, allowing you to write clean and readable code.

### ✨ Key Features

- **💬 Complete Messages**: Send and receive text, media, interactive, and template messages
- **📋 Template Management**: Create, list, edit, delete, and send WhatsApp-approved templates
- **📡 Integrated Webhooks**: Receive messages and updates in real-time
- **🔘 Interactive Messages**: Buttons, dropdown lists, location requests, and more
- **📍 Location and Contacts**: Share geographic locations and contact information
- **🎯 Laravel Events**: Native integration with Laravel events
- **⚡ Real-time Broadcasting**: 100% compatible with Laravel Echo and Reverb
- **🔒 Secure and Validated**: Webhook validation, robust error handling
- **📊 Detailed Logs**: Complete logging system for debugging
- **🎨 Fully Customizable**: Extend models, customize webhooks, adapt everything to your needs
- **🌐 Multi-account**: Manage multiple WhatsApp Business accounts simultaneously
- **🚫 User Blocking**: Block, unblock, and list blocked users
- **📱 Phone Number Management**: Register, sync, and manage phone numbers

## 🚀 Quick Installation

### 1. Install the package

```bash
composer require scriptdevelop/whatsapp-manager
```

### 2. Publish configuration

```bash
php artisan vendor:publish --tag=whatsapp-config
php artisan vendor:publish --tag=whatsapp-routes
```

### 3. Configure environment variables

Add to your `.env` file:

```env
WHATSAPP_API_URL=https://graph.facebook.com
WHATSAPP_API_VERSION=v21.0
WHATSAPP_VERIFY_TOKEN=your-verify-token
WHATSAPP_USER_MODEL=App\Models\User
WHATSAPP_BROADCAST_CHANNEL_TYPE=private

# OPTIONAL VARIABLES
META_CLIENT_ID=123456789012345
META_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
META_REDIRECT_URI=https://yourdomain.com/meta/callback
META_SCOPES=whatsapp_business_management,whatsapp_business_messaging
```

### 4. Run migrations

```bash
php artisan migrate
```

### 5. Ready to use!

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

Whatsapp::message()->sendTextMessage(
    phoneNumberId: '123456789',
    countryCode: '57',
    phoneNumber: '3237121901',
    message: 'Hello from Laravel!'
);
```

## 💡 Usage Examples

### Send Text Message

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$message = Whatsapp::message()->sendTextMessage(
    $phone->phone_number_id,
    '57',
    '3237121901',
    'Hello, this is a test message.'
);
```

### Send Image

```php
$file = new \SplFileInfo(storage_path('app/public/image.png'));

$message = Whatsapp::message()->sendImageMessage(
    $phone->phone_number_id,
    '57',
    '3237121901',
    $file
);
```

### Send Message with Buttons

```php
$response = Whatsapp::sendButtonMessage($phone->phone_number_id)
    ->to('57', '31371235638')
    ->withBody('Do you confirm your appointment for tomorrow at 3 PM?')
    ->addButton('confirm', '✅ Confirm')
    ->addButton('reschedule', '🔄 Reschedule')
    ->withFooter('Please select an option')
    ->send();
```

### Register Business Account

```php
$account = Whatsapp::account()->register([
    'api_token' => 'your-access-token',
    'business_id' => 'your-business-account-id'
]);
```

### Create Template

```php
$template = Whatsapp::template()
    ->createUtilityTemplate($account)
    ->setName('order_confirmation')
    ->setLanguage('en')
    ->addHeader('TEXT', 'Order Confirmation')
    ->addBody('Your order {{1}} has been confirmed.', ['12345'])
    ->addFooter('Thank you for your purchase!')
    ->save();
```

## 📚 Complete Documentation

For complete documentation, detailed examples, advanced configuration guides, and more information, visit:

### 🌐 [Official Documentation](https://laravelwhatsappmanager.com/docs/en)

The official documentation includes:

- 📖 **Complete Installation**: Detailed step-by-step guide
- 🔧 **API Configuration**: Credentials, webhooks, phone numbers
- 💬 **Message Management**: All message types with examples
- 📋 **Template Management**: Creation, editing, deletion, and sending
- 📡 **Real-time Events**: Laravel Echo and Reverb configuration
- 🧪 **Webhooks**: Configuration and event handling
- 🎨 **Customization**: Model extension and webhook customization
- 🚀 **Advanced Examples**: Real use cases and best practices

---

## ⚠️ Important Legal Notice

This is an **UNOFFICIAL** WhatsApp package

**WhatsApp API Manager** is an independently developed open-source package that provides integration with the official WhatsApp Business Platform API. This project is **NOT affiliated, associated, authorized, endorsed, or officially connected** with WhatsApp LLC, Meta Platforms, Inc. or any of their subsidiaries or affiliates.

### © Property Rights

The official WhatsApp names, WhatsApp logo, and all related trademarks are the exclusive property of WhatsApp LLC and Meta Platforms, Inc.

### 👤 User Responsibility

You are solely responsible for how you use this package. You must ensure compliance with all WhatsApp policies and applicable laws.

### 📋 You must comply with:

- ✓ WhatsApp Business Terms of Service
- ✓ WhatsApp Business Policies
- ✓ Meta Platform Policies
- ✓ All applicable privacy and data protection laws and regulations

**No Warranty:** This software is provided "as is", without warranties of any kind, express or implied. The developers assume no responsibility for damages or losses resulting from the use of this package.

---

## 📢 WhatsApp Policies

🚫 **Important:** 🚫
- Always ensure compliance with [WhatsApp's Policies](https://www.whatsapp.com/legal/business-policy/) and terms of use when using this package.
- Misuse may result in account suspension or legal action by WhatsApp.
- Regularly review policy updates to avoid issues.

---

## ❤️ Support

If you find this project useful, consider supporting its development:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donate%20via-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

---

## 🤝 Contributing

Would you like to improve this package? Your collaboration is essential to keep growing!

### 🚀 How to contribute?

1. **Fork the Repository**
2. **Create a Branch** for your feature (`git checkout -b feature/my-new-feature`)
3. **Make Changes** and commit (`git commit -m "Add my new feature"`)
4. **Push** to your branch (`git push origin feature/my-new-feature`)
5. **Open a Pull Request**

### 💡 Contribution Guidelines

- Follow [Laravel's coding style guide](https://laravel.com/docs/contributions#coding-style)
- Write clear and helpful comments
- Include tests where possible
- If you find a bug, open an [Issue](https://github.com/djdang3r/whatsapp-api-manager/issues) before submitting a PR

---

## 👨‍💻 Support and Contact

Do you have questions, problems, or suggestions? We're here to help!

- 📧 **Email:**  
  [wilfredoperilla@gmail.com](mailto:wilfredoperilla@gmail.com)  
  [support@scriptdevelop.com](mailto:support@scriptdevelop.com)

- 🐞 **Report an Issue:**  
  [Open a GitHub Issue](https://github.com/djdang3r/whatsapp-api-manager/issues)

- 💬 **Ideas or Improvements?**  
  Your feedback and suggestions are welcome to keep improving this project!

---

## 📄 License

This project is licensed under the **MIT License**. See the [LICENSE](LICENSE) file for details.

---

<div align="center">

# 🚀 Developed with ❤️ by [ScriptDevelop](https://scriptdevelop.com)

## ✨ Powering your connection with WhatsApp Business API

---

### 🔥 With support from:

**[@vientoquesurcalosmares](https://github.com/vientoquesurcalosmares)**

</div>

---

---

## 🇪🇸 Español

![WhatsApp Business API Manager](https://raw.githubusercontent.com/djdang3r/whatsapp-api-manager/main/assets/whatsapp-api-cloud.png "WhatsApp Business API Manager")

<div align="center">

### 📚 ¡Documentación Completa Disponible!

<a href="https://laravelwhatsappmanager.com/docs/es">
  <img src="https://img.shields.io/badge/📖_Ver_Documentación_Completa-FF6B6B?style=for-the-badge&logo=bookstack&logoColor=white&labelColor=FF6B6B" alt="Ver Documentación Completa" height="50" />
</a>

**[👉 Haz clic aquí para ver la documentación completa](https://laravelwhatsappmanager.com/docs/es)**

</div>

# WhatsApp Business API Manager para Laravel

## 📖 Descripción

`scriptdevelop/whatsapp-manager` es un paquete completo y elegante diseñado para facilitar la integración de la API de WhatsApp Business en tus proyectos Laravel. Proporciona una interfaz fluida y expresiva que se siente natural en Laravel, permitiéndote escribir código limpio y legible.

### ✨ Características Principales

- **💬 Mensajes Completos**: Envía y recibe mensajes de texto, multimedia, interactivos y de plantilla
- **📋 Gestión de Plantillas**: Crea, lista, edita, elimina y envía plantillas aprobadas por WhatsApp
- **📡 Webhooks Integrados**: Recibe mensajes y actualizaciones en tiempo real
- **🔘 Mensajes Interactivos**: Botones, listas desplegables, solicitudes de ubicación y más
- **📍 Ubicación y Contactos**: Comparte ubicaciones geográficas e información de contactos
- **🎯 Eventos Laravel**: Integración nativa con eventos de Laravel
- **⚡ Broadcasting en Tiempo Real**: 100% compatible con Laravel Echo y Reverb
- **🔒 Seguro y Validado**: Validación de webhooks, manejo robusto de errores
- **📊 Logs Detallados**: Sistema completo de logging para debugging
- **🎨 Totalmente Personalizable**: Extiende modelos, personaliza webhooks, adapta todo a tus necesidades
- **🌐 Multi-cuenta**: Gestiona múltiples cuentas de WhatsApp Business simultáneamente
- **🚫 Bloqueo de Usuarios**: Bloquea, desbloquea y lista usuarios bloqueados
- **📱 Gestión de Números**: Registra, sincroniza y gestiona números telefónicos

## 🚀 Instalación Rápida

### 1. Instalar el paquete

```bash
composer require scriptdevelop/whatsapp-manager
```

### 2. Publicar configuración

```bash
php artisan vendor:publish --tag=whatsapp-config
php artisan vendor:publish --tag=whatsapp-routes
```

### 3. Configurar variables de entorno

Agrega en tu archivo `.env`:

```env
WHATSAPP_API_URL=https://graph.facebook.com
WHATSAPP_API_VERSION=v21.0
WHATSAPP_VERIFY_TOKEN=your-verify-token
WHATSAPP_USER_MODEL=App\Models\User
WHATSAPP_BROADCAST_CHANNEL_TYPE=private

# OPTIONAL VARIABLES
META_CLIENT_ID=123456789012345
META_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
META_REDIRECT_URI=https://yourdomain.com/meta/callback
META_SCOPES=whatsapp_business_management,whatsapp_business_messaging
```

### 4. Ejecutar migraciones

```bash
php artisan migrate
```

### 5. ¡Listo para usar!

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

Whatsapp::message()->sendTextMessage(
    phoneNumberId: '123456789',
    countryCode: '57',
    phoneNumber: '3237121901',
    message: '¡Hola desde Laravel!'
);
```

## 💡 Ejemplos de Uso

### Enviar Mensaje de Texto

```php
use ScriptDevelop\WhatsappManager\Facades\Whatsapp;

$message = Whatsapp::message()->sendTextMessage(
    $phone->phone_number_id,
    '57',
    '3237121901',
    'Hola, este es un mensaje de prueba.'
);
```

### Enviar Imagen

```php
$file = new \SplFileInfo(storage_path('app/public/image.png'));

$message = Whatsapp::message()->sendImageMessage(
    $phone->phone_number_id,
    '57',
    '3237121901',
    $file
);
```

### Enviar Mensaje con Botones

```php
$response = Whatsapp::sendButtonMessage($phone->phone_number_id)
    ->to('57', '31371235638')
    ->withBody('¿Confirmas tu cita para mañana a las 3 PM?')
    ->addButton('confirmar', '✅ Confirmar')
    ->addButton('reagendar', '🔄 Reagendar')
    ->withFooter('Por favor selecciona una opción')
    ->send();
```

### Registrar Cuenta de Negocio

```php
$account = Whatsapp::account()->register([
    'api_token' => 'your-access-token',
    'business_id' => 'your-business-account-id'
]);
```

### Crear Plantilla

```php
$template = Whatsapp::template()
    ->createUtilityTemplate($account)
    ->setName('order_confirmation')
    ->setLanguage('es')
    ->addHeader('TEXT', 'Confirmación de Pedido')
    ->addBody('Tu pedido {{1}} ha sido confirmado.', ['12345'])
    ->addFooter('¡Gracias por tu compra!')
    ->save();
```

## 📚 Documentación Completa

Para la documentación completa, ejemplos detallados, guías de configuración avanzada y más información, visita:

### 🌐 [Documentación Oficial](https://laravelwhatsappmanager.com/docs/es)

La documentación oficial incluye:

- 📖 **Instalación Completa**: Guía paso a paso detallada
- 🔧 **Configuración de API**: Credenciales, webhooks, números telefónicos
- 💬 **Gestión de Mensajes**: Todos los tipos de mensajes con ejemplos
- 📋 **Gestión de Plantillas**: Creación, edición, eliminación y envío
- 📡 **Eventos en Tiempo Real**: Configuración de Laravel Echo y Reverb
- 🧪 **Webhooks**: Configuración y manejo de eventos
- 🎨 **Personalización**: Extensión de modelos y personalización de webhooks
- 🚀 **Ejemplos Avanzados**: Casos de uso reales y mejores prácticas

---

## ⚠️ Aviso Legal Importante

Este es un paquete **NO OFICIAL** de WhatsApp

**WhatsApp API Manager** es un paquete de código abierto desarrollado de forma independiente que proporciona una integración con la API oficial de WhatsApp Business Platform. Este proyecto **NO está afiliado, asociado, autorizado, respaldado ni oficialmente conectado** con WhatsApp LLC, Meta Platforms, Inc. o cualquiera de sus subsidiarias o afiliados.

### © Derechos de Propiedad

Los nombres oficiales WhatsApp, el logotipo de WhatsApp y todas las marcas relacionadas son propiedad exclusiva de WhatsApp LLC y Meta Platforms, Inc.

### 👤 Responsabilidad del Usuario

Tú eres el único responsable de cómo utilizas este paquete. Debes asegurarte de cumplir con todas las políticas de WhatsApp y leyes aplicables.

### 📋 Debes cumplir con:

- ✓ Términos de Servicio de WhatsApp Business
- ✓ Políticas de WhatsApp Business
- ✓ Políticas de la Plataforma de Meta
- ✓ Todas las leyes y regulaciones aplicables de privacidad y protección de datos

**Sin Garantía:** Este software se proporciona "tal cual", sin garantías de ningún tipo, expresas o implícitas. Los desarrolladores no asumen ninguna responsabilidad por daños o pérdidas resultantes del uso de este paquete.

---

## 📢 Políticas de WhatsApp

🚫 **Importante:** 🚫
- Asegúrate de cumplir siempre con las [Políticas de WhatsApp](https://www.whatsapp.com/legal/business-policy/) y sus términos de uso al utilizar este paquete.
- El uso indebido puede resultar en la suspensión de tu cuenta o acciones legales por parte de WhatsApp.
- Revisa periódicamente las actualizaciones de las políticas para evitar inconvenientes.

---

## ❤️ Apoyo

Si este proyecto te resulta útil, considera apoyar su desarrollo:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donar%20con-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

---

## 🤝 Contribuir

¿Te gustaría mejorar este paquete? ¡Tu colaboración es fundamental para seguir creciendo!

### 🚀 ¿Cómo contribuir?

1. **Haz un Fork** del repositorio
2. **Crea una Rama** para tu funcionalidad (`git checkout -b feature/mi-nueva-funcionalidad`)
3. **Realiza tus Cambios** y haz commit (`git commit -m "Agrega mi nueva funcionalidad"`)
4. **Haz Push** a tu rama (`git push origin feature/mi-nueva-funcionalidad`)
5. **Abre un Pull Request**

### 💡 Sugerencias para contribuir

- Sigue la [guía de estilo de código de Laravel](https://laravel.com/docs/contributions#coding-style)
- Escribe comentarios claros y útiles
- Incluye pruebas si es posible
- Si encuentras un bug, abre un [Issue](https://github.com/djdang3r/whatsapp-api-manager/issues) antes de enviar el PR

---

## 👨‍💻 Soporte y Contacto

¿Tienes dudas, problemas o sugerencias? ¡Estamos aquí para ayudarte!

- 📧 **Email:**  
  [wilfredoperilla@gmail.com](mailto:wilfredoperilla@gmail.com)  
  [soporte@scriptdevelop.com](mailto:soporte@scriptdevelop.com)

- 🐞 **Reporta un Issue:**  
  [Abrir un Issue en GitHub](https://github.com/djdang3r/whatsapp-api-manager/issues)

- 💬 **¿Ideas o mejoras?**  
  ¡Tus comentarios y sugerencias son bienvenidos para seguir mejorando este proyecto!

---

## 📄 Licencia

Este proyecto está bajo la licencia **MIT**. Consulta el archivo [LICENSE](LICENSE) para más detalles.

---

<div align="center">

# 🚀 Desarrollado con ❤️ por [ScriptDevelop](https://scriptdevelop.com)

## ✨ Potenciando tu conexión con WhatsApp Business API

---

### 🔥 Con el apoyo de:

**[@vientoquesurcalosmares](https://github.com/vientoquesurcalosmares)**

</div>

---
