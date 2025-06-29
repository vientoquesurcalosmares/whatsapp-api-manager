<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\Flow;
use ScriptDevelop\WhatsappManager\Services\InteractiveButtonBuilder;
use ScriptDevelop\WhatsappManager\Services\InteractiveListBuilder;

/**
 * Clase principal para gestionar los servicios de WhatsApp.
 * Proporciona acceso a los servicios relacionados con números de teléfono, mensajes y cuentas empresariales.
 */
class WhatsappManager
{
    protected MessageDispatcherService $dispatcher;

    public function __construct(MessageDispatcherService $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
    
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

    public function sendButtonMessage(string $phoneNumberId): InteractiveButtonBuilder
    {
        return new InteractiveButtonBuilder($this->dispatcher, $phoneNumberId);
    }

    public function sendListMessage(string $phoneNumberId): InteractiveListBuilder
    {
        return new InteractiveListBuilder($this->dispatcher, $phoneNumberId);
    }

    public function sendCtaUrlMessage(string $phoneNumberId): InteractiveCtaUrlBuilder
    {
        return new InteractiveCtaUrlBuilder($this->dispatcher, $phoneNumberId);
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

    public function webhook()
    {
        return app('whatsapp.service');
    }

    public function deletePhoneNumber(string $phoneNumberId): bool
    {
        return $this->phone()->deletePhoneNumber($phoneNumberId);
    }
}