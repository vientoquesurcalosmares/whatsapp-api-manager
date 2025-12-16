<?php

namespace ScriptDevelop\WhatsappManager\Helpers;

class TranslationHelper
{
    /**
     * Get a translation for the WhatsApp package
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        try {
            $locale = $locale ?? config('whatsapp.locale', config('app.locale', 'en'));
            $translation = __("whatsapp::{$key}", $replace, $locale);

            // If translation key is returned as-is, try without namespace
            if ($translation === "whatsapp::{$key}") {
                $translation = __($key, $replace, $locale);
            }

            return $translation;
        } catch (\Throwable $e) {
            // Return the key itself if all fails
            return $key;
        }
    }

    /**
     * Get a translation with choice support
     *
     * @param string $key
     * @param int|array $number
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    public static function choice(string $key, $number, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? config('whatsapp.locale', config('app.locale', 'en'));

        return trans_choice("whatsapp::{$key}", $number, $replace, $locale);
    }
}
