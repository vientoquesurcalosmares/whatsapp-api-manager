<?php

namespace ScriptDevelop\WhatsappManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ScriptDevelop\WhatsappManager\Services\AccountService account()
 * @method static \ScriptDevelop\WhatsappManager\Services\MessageService message()
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'whatsapp';
    }
}