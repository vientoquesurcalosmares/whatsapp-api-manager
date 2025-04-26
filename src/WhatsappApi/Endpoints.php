<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi;

class Endpoints
{
    const GET_BUSINESS_ACCOUNT = '{whatsapp_business_id}';
    const GET_PHONE_NUMBERS = '{whatsapp_business_id}/phone_numbers';
    // const GET_BUSINESS_PROFILE = '{phone_number_id}/whatsapp_business_profile';
    const GET_BUSINESS_PROFILE = '{phone_number_id}/whatsapp_business_profile';
    
    // Messages
    const SEND_MESSAGE = '{phone_number_id}/messages';
    
    // Media
    const UPLOAD_MEDIA = '{phone_number_id}/media';
    const GET_MEDIA = '{phone_number_id}/media/{media_id}';
    
    // Método para construir URLs con parámetros dinámicos
    public static function build(string $endpoint, array $params = []): string
    {
        $placeholders = array_map(fn($key) => "{{$key}}", array_keys($params));
        
        return str_replace(
            $placeholders,
            array_values($params),
            $endpoint
        );
    }

    // Métodos helper para parámetros comunes
    public static function phoneNumber(string $phoneNumberId): array
    {
        return ['phone_number_id' => $phoneNumberId];
    }
}