<?php

namespace ScriptDevelop\WhatsappManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ScriptDevelop\WhatsappManager\Services\AccountRegistrationService account()
 * @method static \ScriptDevelop\WhatsappManager\Services\MessageDispatcherService message()
 * @method static \ScriptDevelop\WhatsappManager\Services\WhatsappService phone()
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'whatsapp.service'; // Accesor principal cambiado
    }

    public static function message()
    {
        return app('whatsapp.message_dispatcher');
    }

    public static function phone()
    {
        return app('whatsapp.service');
    }
}