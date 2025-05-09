<?php

namespace ScriptDevelop\WhatsappManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ScriptDevelop\WhatsappManager\Services\AccountRegistrationService account()
 * @method static \ScriptDevelop\WhatsappManager\Services\MessageDispatcherService message()
 * @method static \ScriptDevelop\WhatsappManager\Services\WhatsappService phone()
 * @method static \ScriptDevelop\WhatsappManager\Services\TemplateService template()
 * @method static mixed getBusinessAccount(string $whatsappBusinessId)
 * @method static array getPhoneNumbers(string $whatsappBusinessId)
 * @method static array getPhoneNumberDetails(string $phoneNumberId)
 * @method static array getBusinessProfile(string $phoneNumberId)
 */
class Whatsapp extends Facade
{
    /**
     * The facade accessor for the primary WhatsApp service.
     */
    protected static function getFacadeAccessor()
    {
        return 'whatsapp.phone';
    }

    /**
     * Get the phone service instance from the container.
     */
    public static function phone()
    {
        return app('whatsapp.phone');
    }

    /**
     * Get the account registration service instance.
     */
    public static function account()
    {
        return app('whatsapp.account');
    }

    /**
     * Get the message dispatcher service instance.
     */
    public static function message()
    {
        return app('whatsapp.message');
    }

    /**
     * Get the template service instance.
     */
    public static function template()
    {
        return app('whatsapp.template');
    }
}