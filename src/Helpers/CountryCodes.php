<?php

namespace ScriptDevelop\WhatsappManager\Helpers;

use Illuminate\Support\Str;

class CountryCodes
{
    public static function list(): array
    {
        return [
            '1',   // USA, Canadá
            '34',  // España
            '52',  // México
            '54',  // Argentina
            '55',  // Brasil
            '57',  // Colombia
            '58',  // Venezuela
            '51',  // Perú
            '56',  // Chile
            '507', // Panamá
            '506', // Costa Rica
            '593', // Ecuador
            '591', // Bolivia
            '592', // Guyana
            '595', // Paraguay
            '598', // Uruguay
            '502', // Guatemala
            '503', // El Salvador
            '504', // Honduras
            '505', // Nicaragua
            // Agrega más según lo necesites
        ];
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
