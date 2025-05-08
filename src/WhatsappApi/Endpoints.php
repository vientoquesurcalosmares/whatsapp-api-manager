<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi;

class Endpoints
{
    // Business Account Endpoints
    const GET_BUSINESS_ACCOUNT = '{whatsapp_business_id}';
    const GET_PHONE_NUMBERS = '{whatsapp_business_id}/phone_numbers';
    const GET_PHONE_DETAILS = '{phone_number_id}';
    const GET_BUSINESS_PROFILE = '{phone_number_id}/whatsapp_business_profile';

    // Message Endpoints
    const SEND_MESSAGE = '{phone_number_id}/messages';

    // Media Upload Endpoints
    const CREATE_UPLOAD_SESSION = 'app/uploads';
    const UPLOAD_FILE = '{upload_id}';
    const QUERY_UPLOAD_STATUS = '{upload_id}';
    const RETRIEVE_MEDIA_URL = '{media_id}';

    // Helper method to build URLs with dynamic parameters
    public static function build(string $endpoint, array $params = []): string
    {
        $placeholders = array_map(fn($key) => "{{$key}}", array_keys($params));

        return str_replace(
            $placeholders,
            array_values($params),
            $endpoint
        );
    }

    // Helper methods for common parameters
    public static function phoneNumber(string $phoneNumberId): array
    {
        return ['phone_number_id' => $phoneNumberId];
    }

    public static function uploadId(string $uploadId): array
    {
        return ['upload_id' => $uploadId];
    }
}