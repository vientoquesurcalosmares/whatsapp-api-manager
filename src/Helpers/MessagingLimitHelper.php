<?php

namespace ScriptDevelop\WhatsappManager\Helpers;

/**
 * Helper para convertir entre tiers de límite de mensajes y valores numéricos.
 * 
 * Según la documentación de Meta:
 * - TIER_250 = 250 mensajes
 * - TIER_2K = 2000 mensajes
 * - TIER_10K = 10000 mensajes
 * - TIER_100K = 100000 mensajes
 * - TIER_UNLIMITED = ilimitado (null)
 */
class MessagingLimitHelper
{
    /**
     * Convierte el tier de límite de mensajes a su valor numérico.
     * 
     * @param string|null $tier El tier del límite de mensajes (ej: "TIER_2K", "TIER_UNLIMITED").
     * @return int|null El número de mensajes correspondiente o null si es ilimitado/desconocido.
     */
    public static function convertTierToLimitValue(?string $tier): ?int
    {
        if ($tier === null) {
            return null;
        }

        return match ($tier) {
            'TIER_250' => 250,
            'TIER_2K' => 2000,
            'TIER_10K' => 10000,
            'TIER_100K' => 100000,
            'TIER_UNLIMITED' => null, // null representa ilimitado
            default => null, // Para valores desconocidos
        };
    }

    /**
     * Convierte un valor numérico de límite a su tier correspondiente.
     * 
     * @param int|null $value Valor numérico del límite. Si es -1 o null, retorna TIER_UNLIMITED.
     * @return string|null El tier correspondiente o null si no se puede determinar.
     */
    public static function convertLimitValueToTier(?int $value): ?string
    {
        if ($value === null || $value == -1) {
            return 'TIER_UNLIMITED';
        }

        return match ($value) {
            250 => 'TIER_250',
            2000 => 'TIER_2K',
            10000 => 'TIER_10K',
            100000 => 'TIER_100K',
            default => null,
        };
    }
}

