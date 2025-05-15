<?php

namespace ScriptDevelop\WhatsappManager\Services;

/**
 * Clase principal para gestionar los servicios de WhatsApp.
 * Proporciona acceso a los servicios relacionados con números de teléfono, mensajes y cuentas empresariales.
 */
class WhatsappManager
{
    /**
     * Obtiene el servicio relacionado con los números de teléfono de WhatsApp.
     *
     * @return mixed El servicio de números de teléfono.
     */
    public function phone()
    {
        return app('whatsapp.phone');
    }

    /**
     * Obtiene el servicio relacionado con los mensajes de WhatsApp.
     *
     * @return mixed El servicio de mensajes.
     */
    public function message()
    {
        return app('whatsapp.message');
    }

    /**
     * Obtiene el servicio relacionado con las cuentas empresariales de WhatsApp.
     *
     * @return mixed El servicio de cuentas empresariales.
     */
    public function account()
    {
        return app('whatsapp.account');
    }

    /**
     * Obtiene el servicio del bot de WhatsApp.
     *
     * @return mixed El servicio del bot.
     */
    public function bot()
    {
        return app('whatsapp.bot');
    }
}