<?php

namespace ScriptDevelop\WhatsappManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ScriptDevelop\WhatsappManager\Services\AccountRegistrationService account()
 * @method static \ScriptDevelop\WhatsappManager\Services\WhatsappService phone()
 * @method static \ScriptDevelop\WhatsappManager\Services\MessageDispatcherService message()
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'whatsapp.phone'; // Acceso principal a operaciones generales
    }

    public static function account()
    {
        return app('whatsapp.account');
    }

    public static function message()
    {
        return app('whatsapp.message');
    }
}