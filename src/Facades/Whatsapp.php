<?php

namespace ScriptDevelop\WhatsappManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ScriptDevelop\WhatsappManager\Services\AccountRegistrationService account()
 * @method static \ScriptDevelop\WhatsappManager\Services\WhatsappService message()
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'whatsapp.service'; // Coincide con la clave del servicio principal
    }

    public static function account()
    {
        return app('whatsapp.account');
    }

    public static function message()
    {
        return app('whatsapp.service');
    }
}