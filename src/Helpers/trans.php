<?php

use ScriptDevelop\WhatsappManager\Helpers\TranslationHelper;

if (!function_exists('whatsapp_trans')) {
    /**
     * Translate a message for the WhatsApp package
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function whatsapp_trans(string $key, array $replace = [], ?string $locale = null): string
    {
        try {
            return TranslationHelper::trans($key, $replace, $locale);
        } catch (\Throwable $e) {
            // Fallback: return the key if translation fails
            \Log::error('Translation error for key: ' . $key, ['error' => $e->getMessage()]);
            return $key;
        }
    }
}
