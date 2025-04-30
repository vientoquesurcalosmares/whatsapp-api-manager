<?php

namespace ScriptDevelop\WhatsappManager\Services;

class WhatsappManager
{
    public function phone()
    {
        return app('whatsapp.phone');
    }

    public function message()
    {
        return app('whatsapp.message');
    }

    public function account()
    {
        return app('whatsapp.account');
    }
}