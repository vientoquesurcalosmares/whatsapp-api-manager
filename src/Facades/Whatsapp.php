<?php

namespace ScriptDevelop\WhatsappManager\Facades;

use Illuminate\Support\Facades\Facade;
use ScriptDevelop\WhatsappManager\Services\AccountRegistrationService;

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

    public static function phone()
    {
        return app('whatsapp.service');
    }

    public static function syncPhone(string $accountId, string $phoneNumberId)
    {
        $service = app('whatsapp.service');
        $details = $service->getPhoneNumberDetails($phoneNumberId);
        return app(AccountRegistrationService::class)->syncPhoneNumber($accountId, $details);
    }
}