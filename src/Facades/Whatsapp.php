<?php

namespace ScriptDevelop\WhatsappManager\Facades;

use Illuminate\Support\Facades\Facade;

class Whatsapp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'whatsapp'; // Debe coincidir con la clave del singleton
    }
}