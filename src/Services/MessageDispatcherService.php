<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Enums\MessageStatus;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Message;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;
use Illuminate\Support\Facades\Log; // <-- Agregamos esto

class MessageDispatcherService
{
    public function __construct(
        protected ApiClient $apiClient
    ) {}

    public function sendTextMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $text,
        bool $previewUrl = false
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'text' => $text,
            'previewUrl' => $previewUrl,
        ]);

        $fullPhoneNumber = $countryCode . $phoneNumber;

        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
        $contact = $this->resolveContact($countryCode, $phoneNumber);

        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'text',
            'message_content' => $text,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING
        ]);

        Log::channel('whatsapp')->info('Mensaje creado en base de datos.', ['message_id' => $message->id]);

        try {
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, $text, $previewUrl);
            Log::info("Enviando mensaje de texto");
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails()
            ]);
            return $this->handleError($message, $e);
        }
    }

    public function sendReplayTextMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $text,
        bool $previewUrl = false
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío replica de mensaje.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,//wa_id del mensaje de contexto
            'text' => $text,
            'previewUrl' => $previewUrl,
        ]);

        Log::info('Iniciando envío replica de mensaje.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,//wa_id del mensaje de contexto
            'text' => $text,
            'previewUrl' => $previewUrl,
        ]);

        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);

            Log::error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }

        $fullPhoneNumber = $countryCode . $phoneNumber;

        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
        $contact = $this->resolveContact($countryCode, $phoneNumber);

        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'text',
            'message_content' => $text,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->id, // Relación con el mensaje de contexto
        ]);

        Log::channel('whatsapp')->info('Mensaje creado en base de datos.', ['message_id' => $message->id]);

        try {
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, $text, $previewUrl, $contextMessage->wa_id);
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails()
            ]);
            return $this->handleError($message, $e);
        }
    }

    private function validatePhoneNumber(string $phoneNumberId): WhatsappPhoneNumber
    {
        Log::channel('whatsapp')->info('Validando número de teléfono.', ['phone_number_id' => $phoneNumberId]);

        $phone = WhatsappPhoneNumber::with('businessAccount')
            ->findOrFail($phoneNumberId);

        if (!$phone->businessAccount?->api_token) {
            Log::channel('whatsapp')->error('Número de teléfono sin token API válido.', ['phone_number_id' => $phoneNumberId]);
            throw new \InvalidArgumentException('El número no tiene un token API válido asociado');
        }

        return $phone;
    }

    private function resolveContact(string $countryCode, string $phoneNumber): Contact
    {
        $fullPhoneNumber = $countryCode . $phoneNumber;

        Log::channel('whatsapp')->info('Resolviendo contacto.', ['full_phone_number' => $fullPhoneNumber]);

        $contact = Contact::firstOrCreate(
            [
            'phone_number' => $phoneNumber,
            'country_code' => $countryCode
            ]
        );

        Log::channel('whatsapp')->info('Contacto resuelto.', ['contact_id' => $contact->contact_id]);

        return $contact;
    }

    private function sendViaApi(
        WhatsappPhoneNumber $phone,
        string $to,
        string $text,
        bool $previewUrl,
        ?string $contextMessageId = null
    ): array {
        $endpoint = Endpoints::build(Endpoints::SEND_MESSAGE, [
            'phone_number_id' => $phone->api_phone_number_id
        ]);

        Log::channel('whatsapp')->info('Enviando solicitud a la API de WhatsApp.', [
            'endpoint' => $endpoint,
            'to' => $to,
            'body' => $text,
            'contextMessageId' => $contextMessageId
        ]);

        // Construir el cuerpo de la solicitud
        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $text
            ]
        ];

        // Agregar contexto si se proporciona un mensaje de contexto
        if ($contextMessageId) {
            $data['context'] = [
                'message_id' => $contextMessageId
            ];
        }

        return $this->apiClient->request(
            'POST',
            $endpoint,
            data: $data,
            headers: [
                'Authorization' => 'Bearer ' . $phone->businessAccount->api_token,
                'Content-Type' => 'application/json'
            ]
        );
    }

    private function handleSuccess(Message $message, array $response): Message
    {
        Log::channel('whatsapp')->info('Mensaje enviado exitosamente.', [
            'message_id' => $message->id,
            'api_response' => $response
        ]);

        $message->update([
            'wa_id' => $response['messages'][0]['id'],
            'messaging_product' => $response['messaging_product'],
            'status' => MessageStatus::SENT,
            'json_content' => $response
        ]);

        return $message;
    }

    private function handleError(Message $message, WhatsappApiException $e): Message
    {
        Log::channel('whatsapp')->error('Error al manejar envío de mensaje.', [
            'message_id' => $message->id,
            'error' => $e->getMessage()
        ]);

        $message->update([
            'status' => MessageStatus::FAILED,
            'code_error' => $e->getCode(),
            'title_error' => $e->getMessage(),
            'details_error' => json_encode($e->getDetails()),
            'json_content' => $e->getDetails()
        ]);

        return $message;
    }
}
