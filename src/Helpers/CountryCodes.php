<?php

namespace ScriptDevelop\WhatsappManager\Helpers;

class CountryCodes
{
    public static function list(): array
    {
        return [
            '1'     => 'US/CA',    // Estados Unidos/Canadá
            '34'    => 'ES',       // España
            '52'    => 'MX',       // México
            '54'    => 'AR',       // Argentina
            '55'    => 'BR',       // Brasil
            '57'    => 'CO',       // Colombia
            '58'    => 'VE',       // Venezuela
            '51'    => 'PE',       // Perú
            '56'    => 'CL',       // Chile
            '507'   => 'PA',       // Panamá
            '506'   => 'CR',       // Costa Rica
            '593'   => 'EC',       // Ecuador
            '591'   => 'BO',       // Bolivia
            '592'   => 'GY',       // Guyana
            '595'   => 'PY',       // Paraguay
            '598'   => 'UY',       // Uruguay
            '502'   => 'GT',       // Guatemala
            '503'   => 'SV',       // El Salvador
            '504'   => 'HN',       // Honduras
            '505'   => 'NI',       // Nicaragua
        ];
    }

    public static function codes(): array
    {
        return array_keys(self::list());
    }
}
