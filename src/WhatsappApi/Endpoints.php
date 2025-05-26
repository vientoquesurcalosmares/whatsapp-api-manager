<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi;

use Illuminate\Support\Facades\Log;

/**
 * Class Endpoints
 * Define los endpoints de la API de WhatsApp Business.
 * Proporciona métodos para construir URLs con parámetros dinámicos.
 */
/**
 * @package ScriptDevelop\WhatsappManager\WhatsappApi
 */
class Endpoints
{
    // Business Account Endpoints
    const GET_BUSINESS_ACCOUNT = '{whatsapp_business_id}';
    const GET_BUSINESS_ACCOUNT_SUSCRIPTIONS = '{whatsapp_business_id}/subscribed_apps';
    const GET_PHONE_NUMBERS = '{whatsapp_business_id}/phone_numbers';
    const GET_PHONE_DETAILS = '{phone_number_id}';
    const GET_BUSINESS_PROFILE = '{phone_number_id}/whatsapp_business_profile';

    // Message Endpoints
    const SEND_MESSAGE = '{phone_number_id}/messages';
    const MARK_MESSAGE_AS_READ = '{phone_number_id}/messages/{message_id}';

    // Media Upload Endpoints
    const CREATE_RESUMABLE_UPLOAD_SESSION = 'app/uploads';
    const RESUMABLE_UPLOAD_FILE = '{upload_id}';
    const CREATE_UPLOAD_SESSION = '{app_id}/uploads';
    const SESSION_UPLOAD_MEDIA = '{session_id}';
    const QUERY_RESUMABLE_UPLOAD_STATUS = '{upload_id}';
    const RETRIEVE_MEDIA_URL = '{media_id}';
    const UPLOAD_MEDIA = '{phone_number_id}/media';
    CONST DOWNLOAD_MEDIA = '{media_id}';
    const DELETE_MEDIA = '{media_id}/?phone_number_id={phone_number_id}';

    // Templates Endpoints
    const GET_TEMPLATES = '{waba_id}/message_templates';
    const GET_TEMPLATE = '{template_id}';
    const CREATE_TEMPLATE = '{waba_id}/message_templates';
    const DELETE_TEMPLATE = '{waba_id}/message_templates';

    // Helper method to build URLs with dynamic parameters
    /**
     * Construye una URL a partir de un endpoint y parámetros.
     *
     * @param string $endpoint El endpoint base.
     * @param array $params Parámetros para reemplazar en el endpoint.
     * @return string La URL generada.
     */
    public static function build(string $endpoint, array $params = []): string
    {
        $placeholders = array_map(fn($key) => "{{$key}}", array_keys($params));

        $url = str_replace(
            $placeholders,
            array_values($params),
            $endpoint
        );

        Log::channel('whatsapp')->info('URL generada:', ['url' => $url]);

        return $url;
    }

    // Helper methods for common parameters
    /**
     * Genera un array con el ID de la cuenta empresarial de WhatsApp.
     *
     * @param string $whatsappBusinessId El ID de la cuenta empresarial.
     * @return array El array con el ID de la cuenta empresarial.
     */
    public static function phoneNumber(string $phoneNumberId): array
    {
        return ['phone_number_id' => $phoneNumberId];
    }

    /**
     * Genera un array con el ID de la aplicación.
     *
     * @param string $appId El ID de la aplicación.
     * @return array El array con el ID de la aplicación.
     */
    public static function uploadId(string $uploadId): array
    {
        return ['upload_id' => $uploadId];
    }
}