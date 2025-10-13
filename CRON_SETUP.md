# Configuración de CRON para WhatsApp Template Analytics

## Configuración automática

El paquete incluye un comando para obtener analytics de templates de WhatsApp Business:

```bash
php artisan whatsapp:get-general-template-analytics
```

## Configuración del Schedule

Para configurar la ejecución automática via CRON, agrega lo siguiente a tu archivo `routes/console.php`:

```php
<?php

use Illuminate\Support\Facades\Schedule;

// WhatsApp Template Analytics - Solo si está habilitado en configuración
if (config('whatsapp.crontimes.get_general_template_analytics.enabled', false)) {
    Schedule::command('whatsapp:get-general-template-analytics')
        ->cron(config('whatsapp.crontimes.get_general_template_analytics.schedule', '0 0 * * *'))
        ->onOneServer()
        ->runInBackground()
        ->withoutOverlapping(60) // Evitar solapamientos por 60 minutos
        ->onSuccess(function () {
            Log::info('WhatsApp Template Analytics: Tarea completada exitosamente');
        })
        ->onFailure(function () {
            Log::error('WhatsApp Template Analytics: Tarea falló');
        });
}
```

## Variables de entorno

Agrega estas variables a tu archivo `.env`:

```env
# Habilitar/deshabilitar la tarea CRON
WHATSAPP_CRON_GET_GENERAL_TEMPLATE_ANALYTICS=true

# Programación CRON (por defecto: diario a medianoche)
WHATSAPP_CRONTIME_GET_GENERAL_TEMPLATE_ANALYTICS="0 0 * * *"
```

## Configuración del servidor

No olvides configurar el CRON en tu servidor:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Opciones del comando

El comando acepta las siguientes opciones:

- `--force`: Forzar obtención de 90 días incluso si hay datos
- `--template=TEMPLATE_ID`: Obtener analytics solo para un template específico
- `--days=DAYS`: Número específico de días a obtener (máximo 90)
- `--account=BUSINESS_ID`: Procesar solo una cuenta específica

### Ejemplos:

```bash
# Obtener datos de los últimos 30 días
php artisan whatsapp:get-general-template-analytics --days=30

# Obtener datos de un template específico
php artisan whatsapp:get-general-template-analytics --template=123456789

# Forzar obtención completa de 90 días
php artisan whatsapp:get-general-template-analytics --force

# Procesar solo una cuenta específica
php artisan whatsapp:get-general-template-analytics --account=1234567890
```