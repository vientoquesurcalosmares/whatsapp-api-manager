<?php

namespace ScriptDevelop\WhatsappManager\Helpers;

use Illuminate\Support\Str;

class CountryCodes
{
    public static function list(): array
    {
        // Códigos de país predefinidos
        $defaultCodes = [
            '1'     => 'US/CA',    // Estados Unidos/Canadá
            '7'     => 'RU',       // Rusia
            '20'    => 'EG',       // Egipto
            '27'    => 'ZA',       // Sudáfrica
            '30'    => 'GR',       // Grecia
            '31'    => 'NL',       // Países Bajos
            '32'    => 'BE',       // Bélgica
            '33'    => 'FR',       // Francia
            '34'    => 'ES',       // España
            '36'    => 'HU',       // Hungría
            '39'    => 'IT',       // Italia
            '40'    => 'RO',       // Rumania
            '41'    => 'CH',       // Suiza
            '43'    => 'AT',       // Austria
            '44'    => 'GB',       // Reino Unido
            '45'    => 'DK',       // Dinamarca
            '46'    => 'SE',       // Suecia
            '47'    => 'NO',       // Noruega
            '48'    => 'PL',       // Polonia
            '49'    => 'DE',       // Alemania
            '51'    => 'PE',       // Perú
            '52'    => 'MX',       // México
            '54'    => 'AR',       // Argentina
            '55'    => 'BR',       // Brasil
            '56'    => 'CL',       // Chile
            '57'    => 'CO',       // Colombia
            '58'    => 'VE',       // Venezuela
            '60'    => 'MY',       // Malasia
            '61'    => 'AU',       // Australia
            '62'    => 'ID',       // Indonesia
            '63'    => 'PH',       // Filipinas
            '64'    => 'NZ',       // Nueva Zelanda
            '65'    => 'SG',       // Singapur
            '66'    => 'TH',       // Tailandia
            '81'    => 'JP',       // Japón
            '82'    => 'KR',       // Corea del Sur
            '84'    => 'VN',       // Vietnam
            '86'    => 'CN',       // China
            '90'    => 'TR',       // Turquía
            '91'    => 'IN',       // India
            '92'    => 'PK',       // Pakistán
            '93'    => 'AF',       // Afganistán
            '94'    => 'LK',       // Sri Lanka
            '95'    => 'MM',       // Myanmar
            '98'    => 'IR',       // Irán
            '212'   => 'MA',       // Marruecos
            '213'   => 'DZ',       // Argelia
            '216'   => 'TN',       // Túnez
            '218'   => 'LY',       // Libia
            '220'   => 'GM',       // Gambia
            '221'   => 'SN',       // Senegal
            '222'   => 'MR',       // Mauritania
            '223'   => 'ML',       // Malí
            '224'   => 'GN',       // Guinea
            '225'   => 'CI',       // Costa de Marfil
            '226'   => 'BF',       // Burkina Faso
            '227'   => 'NE',       // Níger
            '228'   => 'TG',       // Togo
            '229'   => 'BJ',       // Benín
            '230'   => 'MU',       // Mauricio
            '231'   => 'LR',       // Liberia
            '232'   => 'SL',       // Sierra Leona
            '233'   => 'GH',       // Ghana
            '234'   => 'NG',       // Nigeria
            '235'   => 'TD',       // Chad
            '236'   => 'CF',       // República Centroafricana
            '237'   => 'CM',       // Camerún
            '238'   => 'CV',       // Cabo Verde
            '239'   => 'ST',       // Santo Tomé y Príncipe
            '240'   => 'GQ',       // Guinea Ecuatorial
            '241'   => 'GA',       // Gabón
            '242'   => 'CG',       // República del Congo
            '243'   => 'CD',       // República Democrática del Congo
            '244'   => 'AO',       // Angola
            '245'   => 'GW',       // Guinea-Bissau
            '246'   => 'IO',       // Territorio Británico del Océano Índico
            '248'   => 'SC',       // Seychelles
            '249'   => 'SD',       // Sudán
            '250'   => 'RW',       // Ruanda
            '251'   => 'ET',       // Etiopía
            '252'   => 'SO',       // Somalia
            '253'   => 'DJ',       // Yibuti
            '254'   => 'KE',       // Kenia
            '255'   => 'TZ',       // Tanzania
            '256'   => 'UG',       // Uganda
            '257'   => 'BI',       // Burundi
            '258'   => 'MZ',       // Mozambique
            '260'   => 'ZM',       // Zambia
            '261'   => 'MG',       // Madagascar
            '262'   => 'RE',       // Reunión
            '263'   => 'ZW',       // Zimbabue
            '264'   => 'NA',       // Namibia
            '265'   => 'MW',       // Malaui
            '266'   => 'LS',       // Lesotho
            '267'   => 'BW',       // Botsuana
            '268'   => 'SZ',       // Esuatini
            '269'   => 'KM',       // Comoras
            '290'   => 'SH',       // Santa Elena
            '291'   => 'ER',       // Eritrea
            '297'   => 'AW',       // Aruba
            '298'   => 'FO',       // Islas Feroe
            '299'   => 'GL',       // Groenlandia
            '350'   => 'GI',       // Gibraltar
            '351'   => 'PT',       // Portugal
            '352'   => 'LU',       // Luxemburgo
            '353'   => 'IE',       // Irlanda
            '354'   => 'IS',       // Islandia
            '355'   => 'AL',       // Albania
            '356'   => 'MT',       // Malta
            '357'   => 'CY',       // Chipre
            '358'   => 'FI',       // Finlandia
            '359'   => 'BG',       // Bulgaria
            '370'   => 'LT',       // Lituania
            '371'   => 'LV',       // Letonia
            '372'   => 'EE',       // Estonia
            '373'   => 'MD',       // Moldavia
            '374'   => 'AM',       // Armenia
            '375'   => 'BY',       // Bielorrusia
            '376'   => 'AD',       // Andorra
            '377'   => 'MC',       // Mónaco
            '378'   => 'SM',       // San Marino
            '379'   => 'VA',       // Ciudad del Vaticano
            '380'   => 'UA',       // Ucrania
            '381'   => 'RS',       // Serbia
            '382'   => 'ME',       // Montenegro
            '383'   => 'XK',       // Kosovo
            '385'   => 'HR',       // Croacia
            '386'   => 'SI',       // Eslovenia
            '387'   => 'BA',       // Bosnia y Herzegovina
            '389'   => 'MK',       // Macedonia del Norte
            '420'   => 'CZ',       // República Checa
            '421'   => 'SK',       // Eslovaquia
            '423'   => 'LI',       // Liechtenstein
            '500'   => 'FK',       // Islas Malvinas
            '501'   => 'BZ',       // Belice
            '502'   => 'GT',       // Guatemala
            '503'   => 'SV',       // El Salvador
            '504'   => 'HN',       // Honduras
            '505'   => 'NI',       // Nicaragua
            '506'   => 'CR',       // Costa Rica
            '507'   => 'PA',       // Panamá
            '508'   => 'PM',       // San Pedro y Miquelón
            '509'   => 'HT',       // Haití
            '590'   => 'GP',       // Guadalupe/San Bartolomé/San Martín
            '591'   => 'BO',       // Bolivia
            '592'   => 'GY',       // Guyana
            '593'   => 'EC',       // Ecuador
            '594'   => 'GF',       // Guayana Francesa
            '595'   => 'PY',       // Paraguay
            '596'   => 'MQ',       // Martinica
            '597'   => 'SR',       // Surinam
            '598'   => 'UY',       // Uruguay
            '599'   => 'CW',       // Curazao/Bonaire
            '670'   => 'TL',       // Timor Oriental
            '672'   => 'AQ',       // Antártida
            '673'   => 'BN',       // Brunéi
            '674'   => 'NR',       // Nauru
            '675'   => 'PG',       // Papúa Nueva Guinea
            '676'   => 'TO',       // Tonga
            '677'   => 'SB',       // Islas Salomón
            '678'   => 'VU',       // Vanuatu
            '679'   => 'FJ',       // Fiyi
            '680'   => 'PW',       // Palaos
            '681'   => 'WF',       // Wallis y Futuna
            '682'   => 'CK',       // Islas Cook
            '683'   => 'NU',       // Niue
            '684'   => 'AS',       // Samoa Americana
            '685'   => 'WS',       // Samoa
            '686'   => 'KI',       // Kiribati
            '687'   => 'NC',       // Nueva Caledonia
            '688'   => 'TV',       // Tuvalu
            '689'   => 'PF',       // Polinesia Francesa
            '690'   => 'TK',       // Tokelau
            '691'   => 'FM',       // Micronesia
            '692'   => 'MH',       // Islas Marshall
            '850'   => 'KP',       // Corea del Norte
            '852'   => 'HK',       // Hong Kong
            '853'   => 'MO',       // Macao
            '855'   => 'KH',       // Camboya
            '856'   => 'LA',       // Laos
            '880'   => 'BD',       // Bangladesh
            '886'   => 'TW',       // Taiwán
            '960'   => 'MV',       // Maldivas
            '961'   => 'LB',       // Líbano
            '962'   => 'JO',       // Jordania
            '963'   => 'SY',       // Siria
            '964'   => 'IQ',       // Irak
            '965'   => 'KW',       // Kuwait
            '966'   => 'SA',       // Arabia Saudí
            '967'   => 'YE',       // Yemen
            '968'   => 'OM',       // Omán
            '970'   => 'PS',       // Palestina
            '971'   => 'AE',       // Emiratos Árabes Unidos
            '972'   => 'IL',       // Israel
            '973'   => 'BH',       // Baréin
            '974'   => 'QA',       // Catar
            '975'   => 'BT',       // Bután
            '976'   => 'MN',       // Mongolia
            '977'   => 'NP',       // Nepal
            '992'   => 'TJ',       // Tayikistán
            '993'   => 'TM',       // Turkmenistán
            '994'   => 'AZ',       // Azerbaiyán
            '995'   => 'GE',       // Georgia
            '996'   => 'KG',       // Kirguistán
            '998'   => 'UZ',       // Uzbekistán
            '1242'  => 'BS',       // Bahamas
            '1246'  => 'BB',       // Barbados
            '1264'  => 'AI',       // Anguila
            '1268'  => 'AG',       // Antigua y Barbuda
            '1284'  => 'VG',       // Islas Vírgenes Británicas
            '1340'  => 'VI',       // Islas Vírgenes de Estados Unidos
            '1345'  => 'KY',       // Islas Caimán
            '1441'  => 'BM',       // Bermudas
            '1473'  => 'GD',       // Granada
            '1649'  => 'TC',       // Islas Turcas y Caicos
            '1664'  => 'MS',       // Montserrat
            '1670'  => 'MP',       // Islas Marianas del Norte
            '1671'  => 'GU',       // Guam
            '1721'  => 'SX',       // Sint Maarten
            '1758'  => 'LC',       // Santa Lucía
            '1767'  => 'DM',       // Dominica
            '1784'  => 'VC',       // San Vicente y las Granadinas
            '1787'  => 'PR',       // Puerto Rico
            '1809'  => 'DO',       // República Dominicana
            '1829'  => 'DO',       // República Dominicana (adicional)
            '1868'  => 'TT',       // Trinidad y Tobago
            '1869'  => 'KN',       // San Cristóbal y Nieves
            '1876'  => 'JM',       // Jamaica
            '1939'  => 'PR',       // Puerto Rico (adicional)
        ];

        // Obtener códigos personalizados del archivo de configuración
        $customCodes = config('whatsapp.custom_country_codes', []);

        // Combinar códigos: los personalizados sobrescriben los predefinidos
        return array_merge($defaultCodes, $customCodes);
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

