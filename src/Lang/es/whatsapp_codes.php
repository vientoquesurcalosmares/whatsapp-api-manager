<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Códigos de error de la API de la nube de WhatsApp
    |--------------------------------------------------------------------------
    |
    | Fuente: https://developers.facebook.com/documentation/business-messaging/whatsapp/support/error-codes
    | Cada entrada contiene:
    |   'title'    => Título corto del error
    |   'detail'   => Descripción del error y causa probable
    |   'solution' => Solución sugerida
    |
    */

    // -------------------------------------------------------------------------
    // Errores de autorización
    // -------------------------------------------------------------------------

    0 => [
        'title'    => 'Excepción de autenticación',
        'detail'   => 'No se pudo autenticar al usuario de la app. Por lo general se debe a que el token de acceso caducó o se invalidó, o bien a que el usuario cambió una configuración para evitar que todas las apps accedan a sus datos.',
        'solution' => 'Obtén un nuevo token de acceso.',
    ],

    3 => [
        'title'    => 'Método de la API',
        'detail'   => 'Problema de función o permisos.',
        'solution' => 'Usa el depurador de tokens de acceso para comprobar que tu app tenga los permisos requeridos. Consulta Errores de autenticación y autorización.',
    ],

    10 => [
        'title'    => 'Permiso denegado',
        'detail'   => 'No se otorgó el permiso o se eliminó.',
        'solution' => 'Usa el depurador de tokens de acceso para comprobar que tu app tenga los permisos requeridos. Para WhatsApp Flows con punto de conexión, asegúrate de que el número de teléfono usado para configurar la clave pública de la empresa esté autorizado.',
    ],

    190 => [
        'title'    => 'El token de acceso caducó',
        'detail'   => 'El token de acceso ha caducado.',
        'solution' => 'Obtén un nuevo token de acceso.',
    ],

    // Códigos 200–299
    '200_299' => [
        'title'    => 'Permiso de la API',
        'detail'   => 'No se otorgó el permiso o se eliminó.',
        'solution' => 'Usa el depurador de tokens de acceso para comprobar que tu app tenga los permisos requeridos.',
    ],

    // -------------------------------------------------------------------------
    // Errores de integridad
    // -------------------------------------------------------------------------

    368 => [
        'title'    => 'Bloqueado temporalmente por infracción de las políticas',
        'detail'   => 'La cuenta de WhatsApp Business asociada con la app fue restringida o deshabilitada debido a una infracción de la política de la plataforma.',
        'solution' => 'Consulta el documento Aplicación de políticas para obtener información sobre las infracciones y cómo resolverlas.',
    ],

    130497 => [
        'title'    => 'La cuenta de empresa no puede enviar mensajes a usuarios de este país',
        'detail'   => 'La cuenta de WhatsApp Business no puede enviar mensajes a usuarios de determinados países.',
        'solution' => 'Consulta la Política de mensajes de WhatsApp Business para obtener información sobre los países permitidos según la categoría del negocio.',
    ],

    131031 => [
        'title'    => 'Se bloqueó la cuenta',
        'detail'   => 'La cuenta de WhatsApp Business fue restringida o deshabilitada por infracción de una política, o no se pudieron verificar los datos de la solicitud (por ejemplo, PIN de verificación en dos pasos incorrecto).',
        'solution' => 'Consulta el documento Aplicación de políticas. También puedes usar la API de estado general para obtener información adicional sobre el motivo del bloqueo.',
    ],

    // -------------------------------------------------------------------------
    // Errores de limitación / tasa de peticiones
    // -------------------------------------------------------------------------

    4 => [
        'title'    => 'Demasiadas llamadas a la API',
        'detail'   => 'La app alcanzó el límite de frecuencia de llamadas a la API.',
        'solution' => 'Carga la app en el panel de apps y consulta la sección Límite de frecuencia de la app. Si se alcanzó el límite, vuelve a intentarlo más tarde o reduce la frecuencia de consultas.',
    ],

    80007 => [
        'title'    => 'Problemas de límite de frecuencia',
        'detail'   => 'La cuenta de WhatsApp Business alcanzó el límite de frecuencia.',
        'solution' => 'Consulta Límites de frecuencia de la cuenta de WhatsApp Business. Vuelve a intentarlo más tarde o reduce la frecuencia de consultas.',
    ],

    130429 => [
        'title'    => 'Se alcanzó el límite de frecuencia',
        'detail'   => 'Se alcanzó el límite de mensajes de la API de la nube.',
        'solution' => 'Vuelve a intentarlo más tarde o reduce la frecuencia con la que la app envía mensajes. Consulta Rendimiento.',
    ],

    131048 => [
        'title'    => 'Se alcanzó el límite de frecuencia de spam',
        'detail'   => 'No se pudo enviar el mensaje debido a restricciones en la cantidad de mensajes que pueden enviarse desde este número de teléfono. Esto puede deberse a que demasiados mensajes anteriores fueron bloqueados o marcados como spam.',
        'solution' => 'Comprueba el estado de calidad en el administrador de WhatsApp. Consulta Límites de plantillas y Calidad de plantilla.',
    ],

    131056 => [
        'title'    => 'Se alcanzó el límite de frecuencia de la combinación cuenta de empresa / cuenta de cliente',
        'detail'   => 'Se enviaron demasiados mensajes desde el número del emisor al mismo número del destinatario en poco tiempo.',
        'solution' => 'Espera y reintenta la operación si deseas enviar mensajes al mismo número. Aún puedes enviar mensajes a números diferentes sin esperar.',
    ],

    131064 => [
        'title'    => 'Se alcanzó el límite de mensajes por infracciones de clasificación de plantillas',
        'detail'   => 'No se pudo enviar el mensaje porque esta cuenta alcanzó su límite de mensajes por infracciones de clasificación de plantillas. Se aplica tanto a mensajes de plantilla como a mensajes de envío directo.',
        'solution' => 'Revisa las clasificaciones de tus plantillas y asegúrate de que estén categorizadas correctamente. Esta restricción se levanta automáticamente después del período de aplicación. Consulta Calidad de la plantilla.',
    ],

    133016 => [
        'title'    => 'Se excedió el límite de frecuencia de registro/anulación del registro',
        'detail'   => 'Falló el registro o la anulación del registro debido a demasiados intentos en poco tiempo para este número de teléfono.',
        'solution' => 'El número de teléfono del negocio fue bloqueado por alcanzar el límite de intentos. Inténtalo nuevamente cuando el número esté desbloqueado. Consulta "Limitaciones" en el documento Registro.',
    ],

    // -------------------------------------------------------------------------
    // Otros errores de mensajería
    // -------------------------------------------------------------------------

    1 => [
        'title'    => 'API desconocida',
        'detail'   => 'Solicitud no válida o posible error del servidor.',
        'solution' => 'Consulta la página Estado de la Plataforma de WhatsApp Business. Si el servidor no está caído, revisa la referencia del punto de conexión y verifica que la solicitud tenga el formato correcto.',
    ],

    2 => [
        'title'    => 'Servicio de API',
        'detail'   => 'Error temporal debido a que el servidor está caído o sobrecargado.',
        'solution' => 'Consulta la página Estado de la Plataforma de WhatsApp Business antes de volver a intentarlo.',
    ],

    33 => [
        'title'    => 'El valor del parámetro no es válido',
        'detail'   => 'El número de teléfono de la empresa fue eliminado.',
        'solution' => 'Verifica que el número de teléfono de la empresa sea correcto.',
    ],

    100 => [
        'title'    => 'Parámetro no válido',
        'detail'   => 'En la solicitud se incluyeron uno o más parámetros no admitidos o mal escritos.',
        'solution' => 'Consulta la referencia del punto de conexión para determinar los parámetros admitidos. Asegúrate de que no haya incongruencias entre el identificador del número de teléfono que estás registrando y el almacenado anteriormente.',
    ],

    130472 => [
        'title'    => 'El número del usuario es parte de un experimento',
        'detail'   => 'El mensaje no se envió como parte de un experimento.',
        'solution' => 'Consulta Experimento con mensaje de marketing.',
    ],

    131000 => [
        'title'    => 'Se produjo un error',
        'detail'   => 'No se pudo enviar el mensaje debido a un error desconocido.',
        'solution' => 'Inténtalo nuevamente. Si el error persiste, abre un ticket de asistencia directa.',
    ],

    131005 => [
        'title'    => 'Acceso denegado',
        'detail'   => 'No se otorgó el permiso o se eliminó.',
        'solution' => 'Usa el depurador de tokens de acceso para comprobar los permisos.',
    ],

    131008 => [
        'title'    => 'Falta un parámetro obligatorio',
        'detail'   => 'Falta un parámetro obligatorio en la solicitud.',
        'solution' => 'Consulta la referencia del punto de conexión para determinar cuáles son los parámetros obligatorios.',
    ],

    131009 => [
        'title'    => 'El valor del parámetro no es válido',
        'detail'   => 'Uno o más valores del parámetro no son válidos.',
        'solution' => 'Consulta la referencia del punto de conexión para determinar qué valores son compatibles con cada parámetro.',
    ],

    131016 => [
        'title'    => 'Servicio no disponible',
        'detail'   => 'Un servicio no está disponible temporalmente.',
        'solution' => 'Consulta la página Estado de la Plataforma de WhatsApp Business antes de volver a intentarlo.',
    ],

    131021 => [
        'title'    => 'El destinatario no puede ser el emisor',
        'detail'   => 'El número de teléfono del emisor y el del destinatario son el mismo.',
        'solution' => 'Envía un mensaje a un número de teléfono que no sea el del emisor.',
    ],

    131026 => [
        'title'    => 'El mensaje no se puede enviar',
        'detail'   => 'No se puede entregar el mensaje. Posibles razones: el número del destinatario no es un número de WhatsApp, el destinatario no aceptó los Términos del servicio, o está usando una versión antigua de WhatsApp.',
        'solution' => 'Usa otro canal de comunicación y pide al usuario que confirme que puede enviarte mensajes y que aceptó los Términos del servicio.',
    ],

    131037 => [
        'title'    => 'El número de WhatsApp necesita un nombre visible aprobado antes de enviar',
        'detail'   => 'El número de teléfono de empresa no tiene un nombre visible aprobado.',
        'solution' => 'Cambia el nombre visible del número de teléfono del negocio.',
    ],

    131042 => [
        'title'    => 'Elegibilidad de la empresa: problema de pago',
        'detail'   => 'Hubo un error relacionado con tu método de pago. Problemas comunes: sin cuenta de pago adjunta, línea de crédito superada, línea de crédito no configurada, WABA eliminada o suspendida, zona horaria o divisa no configurada, solicitud de MessagingFor pendiente o rechazada.',
        'solution' => 'Consulta Información sobre la facturación de una cuenta de WhatsApp Business y verifica que la facturación esté configurada correctamente.',
    ],

    131045 => [
        'title'    => 'Certificado incorrecto',
        'detail'   => 'No se pudo enviar el mensaje debido a un error de registro del número de teléfono.',
        'solution' => 'Registra el número de teléfono antes de volver a intentarlo.',
    ],

    131047 => [
        'title'    => 'Mensajes de nueva interacción',
        'detail'   => 'Pasaron más de 24 horas desde la última vez que el destinatario envió una respuesta al número del emisor.',
        'solution' => 'En su lugar, envía al destinatario un mensaje de plantilla.',
    ],

    131049 => [
        'title'    => 'Meta decidió no entregar el mensaje',
        'detail'   => 'Este mensaje no se entregó para mantener una interacción adecuada en el ecosistema.',
        'solution' => 'Si sospechas que se debe a un límite, espera al menos 24 horas antes de volver a enviar el mensaje de plantilla. Consulta Límites de mensajes de plantillas de marketing por usuario.',
    ],

    131050 => [
        'title'    => 'El usuario dejó de recibir mensajes de marketing',
        'detail'   => 'Este destinatario eligió dejar de recibir mensajes de marketing de tu negocio en WhatsApp.',
        'solution' => 'No vuelvas a intentar enviar mensajes a este usuario. Suscríbete al webhook user_preferences para recibir notificaciones de cancelación de suscripción.',
    ],

    131051 => [
        'title'    => 'Tipo de mensaje no compatible',
        'detail'   => 'Tipo de mensaje no admitido.',
        'solution' => 'Consulta Mensajes para obtener información sobre los tipos de mensajes compatibles antes de reintentar con uno compatible.',
    ],

    131052 => [
        'title'    => 'Error de descarga del archivo multimedia',
        'detail'   => 'No se pudo descargar el contenido multimedia enviado por el usuario.',
        'solution' => 'Consulta el valor error.error_data.details en los webhooks de mensajes. Pide al usuario que envíe el archivo por un canal diferente a WhatsApp.',
    ],

    131053 => [
        'title'    => 'Error al subir el archivo multimedia',
        'detail'   => 'No se pudo subir el contenido multimedia usado en el mensaje. Posibles razones: tipo de contenido multimedia no admitido.',
        'solution' => 'Inspecciona los archivos multimedia con errores para confirmar que son de tipos admitidos. Consulta los tipos de archivos multimedia compatibles.',
    ],

    131057 => [
        'title'    => 'Cuenta en modo de mantenimiento',
        'detail'   => 'La cuenta comercial está en modo de mantenimiento, posiblemente porque está actualizando su rendimiento.',
        'solution' => 'Espera y vuelve a intentarlo. Si el problema persiste, contacta al soporte.',
    ],

    131063 => [
        'title'    => 'Plantillas de marketing desactivadas para la API de la nube',
        'detail'   => 'Tu plantilla está categorizada como de marketing, pero las plantillas de marketing están desactivadas actualmente para la configuración de la API de la nube.',
        'solution' => 'Usa el punto de conexión de mensajes de marketing o vuelve a habilitar las plantillas de marketing configurando disable_marketing_messages_on_cloud_api en false.',
    ],

    // -------------------------------------------------------------------------
    // Errores de plantillas
    // -------------------------------------------------------------------------

    132000 => [
        'title'    => 'No coincide el conteo de parámetros de la plantilla',
        'detail'   => 'El número de valores de parámetros variables en la solicitud no coincide con el número definido en la plantilla.',
        'solution' => 'Consulta el documento Plantillas para asegurarte de que la solicitud incluya valores para todos los parámetros requeridos.',
    ],

    132001 => [
        'title'    => 'La plantilla no existe',
        'detail'   => 'La plantilla no existe en el idioma especificado o no fue aprobada.',
        'solution' => 'Asegúrate de que la plantilla esté aprobada y de que el nombre e idioma sean correctos.',
    ],

    132005 => [
        'title'    => 'Texto de plantilla traducido demasiado largo',
        'detail'   => 'El texto traducido es demasiado largo.',
        'solution' => 'Comprueba en el administrador de WhatsApp que la plantilla se haya traducido. Consulta Calidad de la plantilla.',
    ],

    132007 => [
        'title'    => 'Se infringió la política de caracteres de formato de plantilla',
        'detail'   => 'El contenido de la plantilla infringe una política de WhatsApp.',
        'solution' => 'Consulta el documento Revisión de plantillas para obtener información sobre los posibles motivos de la infracción.',
    ],

    132012 => [
        'title'    => 'No coincide el formato de los parámetros de la plantilla',
        'detail'   => 'Los valores de parámetros variables tienen un formato incorrecto.',
        'solution' => 'Asegúrate de que los valores de los parámetros variables usen el formato especificado en la plantilla. Consulta el documento Plantillas.',
    ],

    132015 => [
        'title'    => 'La plantilla está en pausa',
        'detail'   => 'La plantilla está en pausa debido a su baja calidad y no puede enviarse.',
        'solution' => 'Edita la plantilla para mejorar su calidad y vuelve a intentarlo una vez que esté aprobada.',
    ],

    132016 => [
        'title'    => 'La plantilla está desactivada',
        'detail'   => 'La plantilla se pausó demasiadas veces debido a su baja calidad y ahora está desactivada de manera permanente.',
        'solution' => 'Crea una nueva plantilla con contenido diferente.',
    ],

    132018 => [
        'title'    => 'Error de validación de plantilla',
        'detail'   => 'Hay un problema con los parámetros de la plantilla.',
        'solution' => 'Revisa los errores, actualiza los parámetros según sea necesario y reenvía el mensaje con una plantilla correctamente configurada.',
    ],

    132068 => [
        'title'    => 'El proceso está bloqueado',
        'detail'   => 'El proceso se encuentra en estado bloqueado.',
        'solution' => 'Corrige el proceso.',
    ],

    132069 => [
        'title'    => 'Proceso limitado',
        'detail'   => 'El proceso está limitado; en la última hora ya se enviaron 10 mensajes usando este proceso.',
        'solution' => 'Corrige el proceso.',
    ],

    // -------------------------------------------------------------------------
    // Errores de registro
    // -------------------------------------------------------------------------

    133000 => [
        'title'    => 'Anulación del registro incompleta',
        'detail'   => 'Falló un intento anterior de anulación del registro.',
        'solution' => 'Anula el registro del número otra vez antes de registrarte.',
    ],

    133004 => [
        'title'    => 'Servidor no disponible temporalmente',
        'detail'   => 'El servidor no está disponible temporalmente.',
        'solution' => 'Consulta la página Estado de la Plataforma de WhatsApp Business y revisa el valor details antes de volver a intentarlo.',
    ],

    133005 => [
        'title'    => 'No coincide el PIN de verificación en dos pasos',
        'detail'   => 'El PIN de verificación en dos pasos es incorrecto.',
        'solution' => 'Comprueba que el PIN sea correcto. Para restablecerlo, desactiva la verificación en dos pasos y establece un nuevo PIN. Consulta Verificación en dos pasos.',
    ],

    133006 => [
        'title'    => 'Es necesario verificar el número de teléfono',
        'detail'   => 'El número de teléfono necesita ser verificado antes de registrarse.',
        'solution' => 'Verifica y registra el número de teléfono.',
    ],

    133008 => [
        'title'    => 'Demasiados intentos incorrectos del PIN de verificación en dos pasos',
        'detail'   => 'Se realizaron demasiados intentos incorrectos del PIN de verificación en dos pasos para este número de teléfono.',
        'solution' => 'Vuelve a intentarlo luego del tiempo especificado en el valor de respuesta details.',
    ],

    133009 => [
        'title'    => 'Intento de PIN de verificación en dos pasos demasiado rápido',
        'detail'   => 'El PIN de verificación en dos pasos se ingresó demasiado rápido.',
        'solution' => 'Consulta el valor de respuesta details antes de volver a intentarlo.',
    ],

    133010 => [
        'title'    => 'Número de teléfono no registrado',
        'detail'   => 'El número de teléfono no está registrado en la Plataforma de WhatsApp Business.',
        'solution' => 'Registra el número de teléfono antes de volver a intentarlo.',
    ],

    133015 => [
        'title'    => 'Espera unos minutos antes de volver a registrar este número',
        'detail'   => 'El número de teléfono que intentas registrar fue eliminado recientemente y la acción aún no se completó.',
        'solution' => 'Espera 5 minutos antes de volver a enviar la solicitud.',
    ],

    // -------------------------------------------------------------------------
    // Errores de pagos
    // -------------------------------------------------------------------------

    134011 => [
        'title'    => 'No se aceptaron las Condiciones del servicio de pagos de WhatsApp',
        'detail'   => 'No se pudo enviar el mensaje porque la aceptación de las Condiciones del servicio de pagos de WhatsApp está pendiente para esta cuenta de WhatsApp Business.',
        'solution' => 'Acepta las Condiciones del servicio de pagos de WhatsApp a través del enlace en el mensaje de error antes de volver a intentarlo.',
    ],

    // -------------------------------------------------------------------------
    // Errores genéricos
    // -------------------------------------------------------------------------

    135000 => [
        'title'    => 'Error de uso genérico',
        'detail'   => 'No se pudo enviar el mensaje debido a un error desconocido relacionado con los parámetros de la solicitud.',
        'solution' => 'Consulta la referencia del punto de conexión para verificar que estás usando la sintaxis correcta. Contacta al soporte si el error persiste.',
    ],

    // -------------------------------------------------------------------------
    // Errores de creación de plantillas
    // -------------------------------------------------------------------------

    2388019 => [
        'title'    => 'Límite de plantillas de mensajes superado',
        'detail'   => 'Superaste el número máximo de plantillas de mensajes para esta cuenta de WhatsApp Business.',
        'solution' => 'Cada cuenta de WhatsApp Business puede tener un máximo de 250 plantillas de mensajes. Consulta Límites de plantillas.',
    ],

    2388040 => [
        'title'    => 'Límite de caracteres superado',
        'detail'   => 'Un campo de la plantilla superó el límite máximo de caracteres permitido.',
        'solution' => 'Consulta el mensaje de error para información específica sobre el campo afectado y sus límites de caracteres.',
    ],

    2388047 => [
        'title'    => 'El formato del encabezado del mensaje es incorrecto',
        'detail'   => 'El encabezado del mensaje contiene un formato no válido.',
        'solution' => 'Consulta el mensaje de error para información específica sobre el formato válido.',
    ],

    2388072 => [
        'title'    => 'El formato del cuerpo del mensaje es incorrecto',
        'detail'   => 'El cuerpo del mensaje contiene un formato no válido.',
        'solution' => 'Consulta el mensaje de error para información específica sobre el formato válido.',
    ],

    2388073 => [
        'title'    => 'El formato del pie de página del mensaje es incorrecto',
        'detail'   => 'El pie de página del mensaje contiene un formato no válido.',
        'solution' => 'Consulta el mensaje de error para información específica sobre el formato válido.',
    ],

    2388293 => [
        'title'    => 'La relación de palabras de los parámetros excede el límite',
        'detail'   => 'Esta plantilla tiene demasiadas variables en relación con su longitud. Reduce el número de variables o aumenta la longitud del mensaje.',
        'solution' => 'Consulta el mensaje de error para información específica sobre el formato válido.',
    ],

    2388299 => [
        'title'    => 'Parámetros iniciales o finales no permitidos',
        'detail'   => 'Las variables no pueden estar al principio ni al final de la plantilla.',
        'solution' => 'Consulta el mensaje de error para información específica sobre el formato válido.',
    ],

    // -------------------------------------------------------------------------
    // Errores de migración de teléfonos
    // -------------------------------------------------------------------------

    2388012 => [
        'title'    => 'Este número de teléfono ya existe en tu lista de números de teléfono',
        'detail'   => 'El número de teléfono que intentas migrar ya existe en tu cuenta de WhatsApp.',
        'solution' => 'Vuelve a intentarlo con un número de teléfono que no esté presente en tu cuenta de WhatsApp.',
    ],

    '2388091_2388093' => [
        'title'    => 'Este número de teléfono no es apto para recibir o verificar un código de registro, ya que no se está migrando',
        'detail'   => 'Las API de verificación de propiedad del teléfono no están disponibles para este caso de uso.',
        'solution' => 'Registra y verifica el número.',
    ],

    '2388103_webhooks' => [
        'title'    => 'No se puede migrar el número de teléfono',
        'detail'   => 'No se configuraron webhooks para la cuenta de WhatsApp Business de destino.',
        'solution' => 'Suscribe tu app a los webhooks en la cuenta de WhatsApp Business de destino.',
    ],

    '2388103_register_directly' => [
        'title'    => 'Agrega este número de teléfono a tu cuenta de WhatsApp',
        'detail'   => 'Este número de teléfono cumple con los requisitos para agregarse directamente y no es necesario usar las API de migración de teléfonos.',
        'solution' => 'Registra y verifica el número.',
    ],

    '2388103_display_name' => [
        'title'    => 'El nombre registrado debe estar presente y aprobado',
        'detail'   => 'El número de teléfono de la empresa debe tener un nombre visible aprobado y no puede tener una solicitud de cambio de nombre visible pendiente.',
        'solution' => 'Obtén la aprobación del nombre visible del número de teléfono del negocio.',
    ],

    '2388103_source_account' => [
        'title'    => 'La cuenta de WhatsApp en la que se registró este número de teléfono no está configurada correctamente',
        'detail'   => 'La cuenta de WhatsApp Business de origen debe estar aprobada, al igual que la opción "Mensajes para".',
        'solution' => 'Es posible que la cuenta de WhatsApp Business esté usando el modelo de propiedad "en nombre de", que ya quedó obsoleto. Contacta al equipo de ayuda.',
    ],

    '2388103_payment_account' => [
        'title'    => 'Tu cuenta de WhatsApp no tiene una cuenta de pago',
        'detail'   => 'Tu cuenta de WhatsApp debe tener una línea de crédito activa para enviar mensajes después de la migración.',
        'solution' => 'Configura una línea de crédito y compártela con el cliente comercial.',
    ],

    '2388103_migration_error' => [
        'title'    => 'Hubo un error al migrar este número de teléfono',
        'detail'   => 'Se produjo un error al intentar migrar tu número de teléfono.',
        'solution' => 'Vuelve a intentarlo después de un tiempo. Si el problema persiste, contacta al equipo de ayuda.',
    ],

    '2388103_different_business' => [
        'title'    => 'Este número de teléfono pertenece a una cuenta diferente del administrador comercial',
        'detail'   => 'Las cuentas de WhatsApp Business de origen y destino deben representar a la misma empresa.',
        'solution' => 'Migra tu número de teléfono a una cuenta de WhatsApp Business que envíe mensajes para la misma empresa que la cuenta de origen.',
    ],

    '2388103_destination_approval' => [
        'title'    => 'Debe aprobarse tu cuenta de WhatsApp',
        'detail'   => 'Para poder migrar números de teléfono, se debe aprobar la cuenta de WhatsApp Business de destino.',
        'solution' => 'Asegúrate de completar la verificación de la empresa y de que el estado de revisión de la cuenta de WhatsApp Business sea aprobado.',
    ],

    '2388103_messaging_for' => [
        'title'    => 'Debe aprobarse la solicitud "Mensajes para" de tu cuenta de WhatsApp',
        'detail'   => 'El cliente debe aprobar la solicitud "Mensajes para" de la cuenta de WhatsApp Business de destino.',
        'solution' => 'Pide a tu cliente que acepte tu solicitud de "Mensajes para" en Meta Business Suite.',
    ],

    2494100 => [
        'title'    => 'La cuenta está en modo de mantenimiento',
        'detail'   => 'El número de teléfono de la empresa está en modo de mantenimiento.',
        'solution' => 'Vuelve a intentarlo en unos minutos.',
    ],

    // -------------------------------------------------------------------------
    // Errores en las estadísticas de plantillas
    // -------------------------------------------------------------------------

    200005 => [
        'title'    => 'Estadísticas de plantillas no disponibles',
        'detail'   => 'Las estadísticas de plantillas aún no están disponibles para esta cuenta de WhatsApp Business.',
        'solution' => 'No puedes activar las estadísticas de plantillas para esta cuenta de WhatsApp Business en este momento.',
    ],

    200006 => [
        'title'    => 'No se pueden desactivar las estadísticas de plantillas',
        'detail'   => 'Operación no válida. Las estadísticas de plantillas no se pueden desactivar una vez que se activan.',
        'solution' => 'Las estadísticas de plantillas no se pueden desactivar una vez que se activan en una cuenta de WhatsApp Business.',
    ],

    200007 => [
        'title'    => 'Las estadísticas de plantillas no están activadas',
        'detail'   => 'Las estadísticas de plantillas no están activadas para esta cuenta de WhatsApp Business.',
        'solution' => 'Para activar las estadísticas de plantillas, consulta Confirmar las estadísticas de plantilla.',
    ],

    // -------------------------------------------------------------------------
    // Errores en cuentas de WhatsApp Business
    // -------------------------------------------------------------------------

    2593079 => [
        'title'    => 'WABA ya marcada para migración',
        'detail'   => 'Esta WABA ya se marcó para migrar a un identificador de solución diferente.',
        'solution' => 'El modelo de propiedad de cuentas OBO es obsoleto. Ponte en contacto con el equipo de ayuda.',
    ],

    2593085 => [
        'title'    => 'Cuenta de WhatsApp Business no válida para la movilidad OBO',
        'detail'   => 'La WABA no es apta para la transferencia de propiedad OBO. Posibles razones: la WABA ya es propiedad del cliente comercial, o el cliente aún no aceptó la solicitud OBO en Meta Business Suite.',
        'solution' => 'El modelo de propiedad de cuentas OBO está obsoleto. Ponte en contacto con el equipo de ayuda.',
    ],

    // -------------------------------------------------------------------------
    // Errores de sincronización
    // -------------------------------------------------------------------------

    2593107 => [
        'title'    => 'Se superó el límite de solicitudes de sincronización',
        'detail'   => 'Superaste la cantidad máxima de llamadas a la API de sincronización para este número de teléfono.',
        'solution' => 'Solo puedes llamar a este punto de conexión una vez para sincronizar contactos y una vez para el historial de mensajes. Consulta Registro de usuarios de apps empresariales. Elimina el cliente comercial y vuelve a registrarlo.',
    ],

    2593108 => [
        'title'    => 'Solicitud de sincronización fuera del período de tiempo permitido',
        'detail'   => 'La solicitud de sincronización solo puede hacerse dentro de las 24 horas posteriores al registro.',
        'solution' => 'Solo puedes iniciar la sincronización dentro de las 24 horas del registro del usuario. Elimina el usuario y vuelve a registrarlo.',
    ],

    // -------------------------------------------------------------------------
    // Errores de la API de mensajes de marketing
    // -------------------------------------------------------------------------

    131055 => [
        'title'    => 'Método no permitido',
        'detail'   => 'Solo se admiten mensajes de plantilla de marketing.',
        'solution' => 'Reenvía el mensaje usando una plantilla de marketing.',
    ],

    134100 => [
        'title'    => 'Solo se admiten mensajes de marketing',
        'detail'   => 'Solo puedes enviar mensajes de marketing en esta API.',
        'solution' => 'Usa una plantilla de categoría MARKETING. Disponible desde la versión 23.0 de la API Graph.',
    ],

    134101 => [
        'title'    => 'La plantilla aún se está sincronizando',
        'detail'   => 'Al enviar un mensaje de plantilla, el proceso de sincronización puede tardar hasta 10 minutos.',
        'solution' => 'Espera unos minutos y vuelve a intentar enviar el mensaje. Disponible desde la versión 23.0 de la API Graph.',
    ],

    134102 => [
        'title'    => 'Plantilla no disponible para su uso',
        'detail'   => 'No se pudo completar la sincronización de anuncios para la plantilla, o es posible que no cumplas los requisitos para la API de mensajes de marketing.',
        'solution' => 'Comprueba tu estado de elegibilidad. Si marketing_messages_lite_api_status es ONBOARDED y el problema continúa, contacta al soporte. Disponible desde la versión 23.0 de la API Graph.',
    ],

    1752041 => [
        'title'    => 'Solicitud duplicada',
        'detail'   => 'Se emite una solicitud duplicada cuando un cliente ya fue invitado a registrarse por algún socio.',
        'solution' => 'Las solicitudes de registro se limitan a una por cliente comercial. Si recibes este error, todas las WABA elegibles de ese cliente se registrarán automáticamente sin acciones adicionales.',
    ],

];
