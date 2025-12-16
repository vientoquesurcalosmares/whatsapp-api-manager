<?php

namespace ScriptDevelop\WhatsappManager\Helpers;

/**
 * Helper to convert between messaging limit tiers and numeric values.
 * Helper para convertir entre tiers de límite de mensajes y valores numéricos.
 * 
 * According to Meta's documentation / Según la documentación de Meta:
 * - TIER_250 = 250 messages / 250 mensajes
 * - TIER_2K = 2000 messages / 2000 mensajes
 * - TIER_10K = 10000 messages / 10000 mensajes
 * - TIER_100K = 100000 messages / 100000 mensajes
 * - TIER_UNLIMITED = unlimited / ilimitado (null)
 */
class MessagingLimitHelper
{
    /**
     * Converts the messaging limit tier to its numeric value.
     * Convierte el tier de límite de mensajes a su valor numérico.
     * 
     * @param string|null $tier The messaging limit tier (e.g: "TIER_2K", "TIER_UNLIMITED"). / El tier del límite de mensajes (ej: "TIER_2K", "TIER_UNLIMITED").
     * @return int|null The number of messages or null if unlimited/unknown. / El número de mensajes correspondiente o null si es ilimitado/desconocido.
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
            'TIER_UNLIMITED' => null, // null represents unlimited / null representa ilimitado
            default => null, // For unknown values / Para valores desconocidos
        };
    }

    /**
     * Converts a numeric limit value to its corresponding tier.
     * Convierte un valor numérico de límite a su tier correspondiente.
     * 
     * @param int|null $value Numeric limit value. If -1 or null, returns TIER_UNLIMITED. / Valor numérico del límite. Si es -1 o null, retorna TIER_UNLIMITED.
     * @return string|null The corresponding tier or null if cannot be determined. / El tier correspondiente o null si no se puede determinar.
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
