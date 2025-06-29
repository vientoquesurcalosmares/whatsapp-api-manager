[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/djdang3r/whatsapp-api-manager)

![Ejemplo de plantilla de marketing](assets/Whatsapp'Manager.png "Plantilla de Marketing")

# WhatsApp Business API Manager for Laravel

LARAVEL WHatsapp Manager

<p align="center">
<a href="https://packagist.org/packages/scriptdevelop/whatsapp-manager"><img src="https://img.shields.io/packagist/v/scriptdevelop/whatsapp-manager.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://php.net/"><img src="https://img.shields.io/badge/PHP-8.2%2B-8892BF.svg?style=flat-square" alt="PHP Version"></a>
<a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-12%2B-FF2D20.svg?style=flat-square" alt="Laravel Version"></a>
<a href="https://packagist.org/packages/scriptdevelop/whatsapp-manager"><img src="https://img.shields.io/packagist/dt/scriptdevelop/whatsapp-manager" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/scriptdevelop/whatsapp-manager"><img src="https://img.shields.io/packagist/l/scriptdevelop/whatsapp-manager" alt="License"></a>
</p>

---

### 🌐 Language / Idioma

<a href="#english"><img src="https://flagcdn.com/us.svg" width="20"></a> [🇺🇸 English](#-english) | <a href="documentation/es/01-instalacion.md" title="Sección siguiente">🇪🇸 Español<img src="https://flagcdn.com/es.svg" width="20"></a>

#### 🇪🇸 Español

---

# scriptdevelop/whatsapp-api-manager

## Introducción

`@djdang3r/whatsapp-api-manager` es un paquete diseñado para facilitar la integración y gestión de la API de WhatsApp en tus proyectos. Su objetivo es simplificar la comunicación, el envío y la recepción de mensajes, así como la administración de sesiones y contactos a través de una interfaz intuitiva y fácil de usar.

## Descripción

Con este paquete podrás:

- Conectarte fácilmente a la API de WhatsApp.
- Enviar y recibir mensajes de texto, multimedia y archivos.
- Gestionar múltiples sesiones de WhatsApp simultáneamente.
- Administrar contactos, plantillas y mensajes.
- Integrar tu aplicación o servicio con flujos automatizados de mensajes.
- Recibir eventos en tiempo real para reaccionar ante mensajes, cambios de estado y notificaciones.

`@djdang3r/whatsapp-api-manager` está pensado para desarrolladores que buscan una solución robusta y flexible para interactuar con WhatsApp de manera eficiente, segura y escalable.

> ## 📢 Políticas de WhatsApp
>
> 🚫 **Importante:** 🚫
> - Asegúrate de cumplir siempre con las [Políticas de WhatsApp](https://www.whatsapp.com/legal/business-policy/) y sus términos de uso al utilizar este paquete.  
> - El uso indebido puede resultar en la suspensión de tu cuenta o acciones legales por parte de WhatsApp.
> - Revisa periódicamente las actualizaciones de las políticas para evitar inconvenientes.


> ## ⚠️ **Advertencia:**  ⚠️
> - Este paquete se encuentra actualmente en versión **alpha**. Esto significa que está en desarrollo activo, puede contener errores y su API está sujeta a cambios importantes.  
> - Próximamente se lanzará la versión **beta**. Se recomienda no usarlo en entornos de producción por el momento.

---

## Documentación

## 📚 Tabla de Contenidos
<a href="documentation/es/01-instalacion.md" title="Documentación en Español">
1. 🚀 Instalación
</a>

   - Requisitos previos
   - Configuración inicial
   - Migraciones

<a href="documentation/es/02-config-api.md" title="Documentación en Español">
2. 🧩 Configuración de API
</a>

   - Credenciales de Meta
   - Configuración de webhooks
   - Gestión de números telefónicos

<a href="documentation/es/03-mensajes.md" title="Documentación en Español">
3. 💬 Gestión de Mensajes
</a>

   - Envío de mensajes (texto, multimedia, ubicación)
   - Mensajes interactivos (botones, listas)
   - Plantillas de mensajes
   - Recepción de mensajes

<a href="documentation/es/04-plantillas.md" title="Documentación en Español">
4. 📋 Gestión de Plantillas
</a>

   - Creación de plantillas
   - Envío de plantillas
   - Administración de versiones

<a href="documentation/es/05-eventos.md" title="Documentación en Español">
5. 📡 Eventos en Tiempo Real
</a>

   - Configuración de Laravel Echo
   - Webhooks integrados
   - Tipos de eventos soportados

<a href="documentation/es/06-webhook.md" title="Documentación en Español">
6. 🧪 Webhook
</a>

   - Configuracion del Webhook
   - Estructura de eventos
   - Tipos de mensajes soportados

---

>## 🚀 Características Principales
>
>- **Envía mensajes** de texto, multimedia, interactivos y de plantilla.
>- **Gestion de Templates** para Crear, Listar, Eliminar y Probar plantillas.
>- **Webhooks integrados** para recibir mensajes y actualizaciones.
>- **Gestión de conversaciones** con métricas de cobro.
>- **Sincronización automática** de números telefónicos y perfiles.
>- 100% compatible con **Laravel Echo y Reverb** para notificaciones en tiempo real.
> 

---

## ❤️ Apoyo

Si este proyecto te resulta útil, considera apoyar su desarrollo:

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)
[![Mercado Pago](https://img.shields.io/badge/Donar%20con-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)

---
>
># 🤝 ¡Contribuye con el Proyecto!
>
>¿Te gustaría mejorar este paquete? ¡Tu colaboración es fundamental >para seguir creciendo!
>
---

## 🚀 ¿Cómo contribuir?

1. **Haz un Fork**
    - Haz clic en el botón `Fork` en la parte superior derecha de este repositorio para crear tu propia copia.

2. **Crea una Rama para tu Funcionalidad**
    ```bash
    git checkout -b feature/mi-nueva-funcionalidad
    ```

3. **Realiza tus Cambios y Haz Commit**
    ```bash
    git commit -m "Agrega mi nueva funcionalidad"
    ```

4. **Haz Push a tu Rama**
    ```bash
    git push origin feature/mi-nueva-funcionalidad
    ```

5. **Abre un Pull Request**
    - Ve a la pestaña `Pull Requests` y haz clic en `New Pull Request`.
    - Describe brevemente tu aporte y por qué es útil.

---
>
>## 💡 Sugerencias para contribuir
>
>- Sigue la [guía de estilo de código de Laravel](https://laravel.com/docs/contributions#coding-style).
>- Escribe comentarios claros y útiles.
>- Incluye pruebas si es posible.
>- Si encuentras un bug, abre un [Issue](https://github.com/djdang3r/whatsapp-api-manager/issues) antes de enviar el PR.
>
---

## 🙌 ¡Gracias por tu apoyo!

Cada contribución, por pequeña que sea, ayuda a mejorar el proyecto y a la comunidad.  
¡No dudes en participar, proponer ideas o reportar problemas!


---

## Descargo de responsabilidad

Este paquete es un proyecto independiente y **no está afiliado, respaldado ni soportado por Meta Platforms, Inc.**  
Todas las marcas registradas, marcas de servicio y logotipos utilizados en esta documentación, incluidos "WhatsApp" y "Facebook", son propiedad de Meta Platforms, Inc.

---

## 📄 Licencia

Este proyecto está bajo la licencia **MIT**. Consulta el archivo [LICENSE](LICENSE) para más detalles.

---

# 👨‍💻 Soporte y Contacto

¿Tienes dudas, problemas o sugerencias?  
¡Estamos aquí para ayudarte!

- 📧 **Email:**  
    [wilfredoperilla@gmail.com](mailto:wilfredoperilla@gmail.com)  
    [soporte@scriptdevelop.com](mailto:soporte@scriptdevelop.com)

- 🐞 **Reporta un Issue:**  
    [Abrir un Issue en GitHub](https://github.com/djdang3r/whatsapp-api-manager/issues)

- 💬 **¿Ideas o mejoras?**  
    ¡Tus comentarios y sugerencias son bienvenidos para seguir mejorando este proyecto!

---

<div align="center">

# 🚀 Desarrollado con ❤️ por [ScriptDevelop](https://scriptdevelop.com)

## ✨ Potenciando tu conexión con WhatsApp Business API

---

### 🔥 Con el apoyo de:

**[@vientoquesurcalosmares](https://github.com/vientoquesurcalosmares)**

</div>

---

## ❤️Apóyanos con una donación en GitHub Sponsors

Me puedes apoyar como desarrollador open source en GitHub Sponsors:
- Si este proyecto te ha sido útil, puedes apoyarlo con una donación a través de
[![Sponsor](https://img.shields.io/badge/Sponsor%20me-GitHub-blue?style=for-the-badge&logo=github)](https://github.com/sponsors/djdang3r)

- O tambien por Mercadopago Colombia.
[![Donar con Mercado Pago](https://img.shields.io/badge/Donar%20con-Mercado%20Pago-blue?style=for-the-badge&logo=mercadopago)](https://mpago.li/2qe5G7E)
Gracias por tu apoyo 💙