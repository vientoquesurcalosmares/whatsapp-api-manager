<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Enums\MessageStatus;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Message;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;
use Illuminate\Support\Facades\Log;

class MessageDispatcherService
{
    public function __construct(
        protected ApiClient $apiClient
    ) {}

    public function sendText(
        string $to,
        string $content,
        bool $previewUrl = false,
        ?string $replyTo = null,
        string $phoneNumberId
    ): Message {
        Log::info('Iniciando envío de mensaje de texto.', [
            'to' => $to,
            'content' => $content,
            'previewUrl' => $previewUrl,
            'replyTo' => $replyTo,
            'phoneNumberId' => $phoneNumberId,
        ]);

        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);

        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'message_to' => $to,
            'message_type' => 'text',
            'message_content' => $content,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);

        try {
            $response = $this->sendViaApi($phoneNumberModel, $to, $content, $previewUrl, $replyTo);
            Log::info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::error('Error al enviar mensaje por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
            return $this->handleError($message, $e);
        }
    }

    public function sendImage(
        string $to,
        string $mediaIdOrUrl,
        bool $isUrl = false,
        ?string $caption = null,
        ?string $replyTo = null,
        string $phoneNumberId
    ): Message {
        Log::info('Iniciando envío de imagen.', [
            'to' => $to,
            'mediaIdOrUrl' => $mediaIdOrUrl,
            'isUrl' => $isUrl,
            'caption' => $caption,
            'replyTo' => $replyTo,
            'phoneNumberId' => $phoneNumberId,
        ]);
    
        // Usa el servicio especializado para manejar imágenes
        $imageService = new \ScriptDevelop\WhatsappManager\Services\Messages\ImageMessageService($this->apiClient);
    
        return $imageService->send(
            $to,
            $mediaIdOrUrl,
            $isUrl,
            $caption,
            $replyTo,
            $phoneNumberId
        );
    }

    private function validatePhoneNumber(string $phoneNumberId): WhatsappPhoneNumber
    {
        Log::info('Validando número de teléfono.', ['phone_number_id' => $phoneNumberId]);

        $phone = WhatsappPhoneNumber::with('businessAccount')
            ->findOrFail($phoneNumberId);

        if (!$phone->businessAccount?->api_token) {
            Log::error('Número de teléfono sin token API válido.', ['phone_number_id' => $phoneNumberId]);
            throw new \InvalidArgumentException('El número no tiene un token API válido asociado');
        }

        return $phone;
    }

    private function sendViaApi(
        WhatsappPhoneNumber $phone,
        string $to,
        string $text,
        bool $previewUrl,
        ?string $replyTo
    ): array {
        $endpoint = Endpoints::build(Endpoints::SEND_MESSAGE, [
            'phone_number_id' => $phone->api_phone_number_id,
        ]);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $text,
            ],
        ];

        if ($replyTo) {
            $payload['context'] = ['message_id' => $replyTo];
        }

        Log::info('Enviando solicitud a la API de WhatsApp.', [
            'endpoint' => $endpoint,
            'payload' => $payload,
        ]);

        return $this->apiClient->request(
            'POST',
            $endpoint,
            data: $payload,
            headers: [
                'Authorization' => 'Bearer ' . $phone->businessAccount->api_token,
                'Content-Type' => 'application/json',
            ]
        );
    }

    private function handleSuccess(Message $message, array $response): Message
    {
        Log::info('Mensaje enviado exitosamente.', [
            'message_id' => $message->id,
            'api_response' => $response,
        ]);

        $message->update([
            'wa_id' => $response['messages'][0]['id'] ?? null,
            'messaging_product' => $response['messaging_product'] ?? null,
            'status' => MessageStatus::SENT,
            'json_content' => $response,
        ]);

        return $message;
    }

    private function handleError(Message $message, WhatsappApiException $e): Message
    {
        Log::error('Error al manejar envío de mensaje.', [
            'message_id' => $message->id,
            'error' => $e->getMessage(),
        ]);

        $message->update([
            'status' => MessageStatus::FAILED,
            'code_error' => $e->getCode(),
            'title_error' => $e->getMessage(),
            'details_error' => json_encode($e->getDetails()),
            'json_content' => $e->getDetails(),
        ]);

        return $message;
    }
}