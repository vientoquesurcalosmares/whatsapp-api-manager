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
        return 'whatsapp.message_dispatcher';
    }

    public static function account()
    {
        return app('whatsapp.account');
    }

    public static function phone()
    {
        return app('whatsapp.service');
    }
}