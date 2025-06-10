<?php

namespace ScriptDevelop\WhatsappManager\Helpers;

use Illuminate\Support\Str;

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

    /**
     * Sirve para normalizar adecuadamente celulares internacionales, aunque actualmente sirve especialmente para el caso de México, y que siempre tenga el 1 entre la lada y los 10 dígitos del ceular, pero también puede servir en el futuro si nos damos cuenta de que hay mas casos especiales, aunque se supone que no debería!
     * @param [type] $countryCode
     * @param [type] $phoneNumber
     * @return array
     */
    public static function normalizeInternationalPhone($countryCode, $phoneNumber): array
    {
        //Si el país es México, según ChatGPT este es el único caso en el mundo que tiene un 1 después del código de area y luego vienen 10 dígitos del celular así 521 1234567890
        //Por lo tanto comprobar si es número de méxico y el $phoneNumber son exactamente 10 números, entonces agregar el 1 inicial
        if( $countryCode==52 && Str::length($phoneNumber)==10 )
        {
            $phoneNumber = '1'.$phoneNumber;
        }

        $fullPhoneNumber = $countryCode . $phoneNumber;

        return [
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'fullPhoneNumber' => $fullPhoneNumber,
        ];
    }
}
