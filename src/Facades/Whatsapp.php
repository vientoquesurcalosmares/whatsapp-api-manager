<?php

namespace ScriptDevelop\WhatsappManager\Facades;

use Illuminate\Support\Facades\Facade;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\Models\Message;

/**
 * @method static \ScriptDevelop\WhatsappManager\Services\AccountRegistrationService account()
 * @method static \ScriptDevelop\WhatsappManager\Services\WhatsappService phone()
 * @method static \ScriptDevelop\WhatsappManager\Services\MessageDispatcherService message()
 * @method static array getPhoneNumbers(string $whatsappBusinessId)
 * @method static array getBusinessProfile(string $phoneNumberId)
 * 
 * @method static Message sendText(
 *     string $to, 
 *     string $content, 
 *     bool $previewUrl = false, 
 *     ?string $replyTo = null
 * )
 * @method static Message sendImage(
 *     string $to,
 *     string $mediaIdOrUrl,
 *     bool $isUrl = false,
 *     ?string $caption = null,
 *     ?string $replyTo = null
 * )
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        // Cambiar el accessor para que apunte a 'whatsapp' (WhatsappManager)
        return 'whatsapp';
    }

    public static function phone(): \ScriptDevelop\WhatsappManager\Services\WhatsappService
    {
        return app('whatsapp')->phone();
    }

    public static function account(): \ScriptDevelop\WhatsappManager\Services\AccountRegistrationService
    {
        return app('whatsapp')->account();
    }

    public static function message(): \ScriptDevelop\WhatsappManager\Services\MessageDispatcherService
    {
        return app('whatsapp')->message();
    }

    // Métodos de mensajería con parámetros explícitos
    public static function sendText(
        string $to,
        string $content,
        bool $previewUrl = false,
        ?string $replyTo = null
    ): Message {
        return self::message()->sendText(
            $to,
            $content,
            $previewUrl,
            $replyTo,
            self::resolvePhoneNumberId() // Añadir como último parámetro
        );
    }

    public static function sendImage(
        string $to,
        string $mediaIdOrUrl,
        bool $isUrl = false,
        ?string $caption = null,
        ?string $replyTo = null
    ): Message {
        return self::message()->sendImage(
            $to,
            $mediaIdOrUrl,
            $isUrl,
            $caption,
            $replyTo,
            self::resolvePhoneNumberId() // Último parámetro requerido
        );
    }

    private static function resolvePhoneNumberId(): string
    {
        $defaultId = config('whatsapp.default_phone_number_id');
        
        if ($defaultId) {
            return $defaultId;
        }

        $phone = WhatsappPhoneNumber::default()->first();
        
        if (!$phone) {
            throw new \RuntimeException(
                'No se encontró número de WhatsApp predeterminado. ' .
                'Configure WHATSAPP_DEFAULT_PHONE_ID en .env o establezca un número como predeterminado'
            );
        }

        return $phone->phone_number_id;
    }
}