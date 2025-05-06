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
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
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
            // Preparar los parámetros para el envío
            $parameters = [
                'preview_url' => $previewUrl,
                'body' => $text,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'text', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails()
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    public function sendReplyTextMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $text,
        bool $previewUrl = false
    ): Message {
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

        Log::info('Mensaje de replica.', ['message' => $contextMessage, 'message_id' => $contextMessage->message_id, 'wa_id' => $contextMessage->wa_id]);

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
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);

        Log::channel('whatsapp')->info('Mensaje creado en base de datos.', ['message_id' => $message->message_id]);

        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'preview_url' => $previewUrl,
                'body' => $text,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'text', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }
    
    public function sendReplyReactionMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $emoji
    ): Message {
        Log::info('Iniciando envío replica de mensaje.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,//wa_id del mensaje de contexto
            'emoji' => $emoji
        ]);

        if (empty($emoji)) {
            Log::channel('whatsapp')->error('El emoji está vacío.');
            throw new \InvalidArgumentException('El emoji no puede estar vacío.');
        }

        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();

        Log::info('Mensaje de replica.', ['message' => $contextMessage, 'message_id' => $contextMessage->message_id, 'wa_id' => $contextMessage->wa_id]);

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
            'message_type' => 'reaction',
            'message_content' => '😂',
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);

        Log::channel('whatsapp')->info('Mensaje creado en base de datos.', ['message_id' => $message->message_id]);

        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'message_id' => $contextMessage->wa_id,
                'emoji' => '😂',
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'reaction', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
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
        string $type,
        array $parameters,
        ?string $contextMessageId = null
    ): array {
        $endpoint = Endpoints::build(Endpoints::SEND_MESSAGE, [
            'phone_number_id' => $phone->api_phone_number_id
        ]);

        Log::info('Enviando solicitud a la API de WhatsApp.', [
            'endpoint' => $endpoint,
            'to' => $to,
            'type' => $type,
            'parameters' => $parameters,
            'contextMessageId' => $contextMessageId
        ]);

        // Construir el cuerpo base de la solicitud
        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
        ];

        // Ensamblar el contenido dinámico según el tipo de mensaje
        switch ($type) {
            case 'text':
                $data['text'] = [
                    'preview_url' => $parameters['preview_url'] ?? false,
                    'body' => $parameters['body'] ?? ''
                ];
                break;

            case 'reaction':
                $data['reaction'] = [
                    'message_id' => $parameters['message_id'] ?? '',
                    'emoji' => $parameters['emoji'] ?? ''
                ];
                break;

            case 'image':
                $data['image'] = $parameters['id'] 
                    ? ['id' => $parameters['id']] 
                    : ['link' => $parameters['link'] ?? ''];
                break;

            case 'audio':
                $data['audio'] = $parameters['id'] 
                    ? ['id' => $parameters['id']] 
                    : ['link' => $parameters['link'] ?? ''];
                break;

            case 'document':
                $data['document'] = $parameters['id'] 
                    ? [
                        'id' => $parameters['id'],
                        'caption' => $parameters['caption'] ?? '',
                        'filename' => $parameters['filename'] ?? ''
                    ]
                    : [
                        'link' => $parameters['link'] ?? '',
                        'caption' => $parameters['caption'] ?? ''
                    ];
                break;

            case 'sticker':
                $data['sticker'] = $parameters['id'] 
                    ? ['id' => $parameters['id']] 
                    : ['link' => $parameters['link'] ?? ''];
                break;

            case 'video':
                $data['video'] = $parameters['id'] 
                    ? [
                        'id' => $parameters['id'],
                        'caption' => $parameters['caption'] ?? ''
                    ]
                    : [
                        'link' => $parameters['link'] ?? '',
                        'caption' => $parameters['caption'] ?? ''
                    ];
                break;

            case 'contacts':
                $data['contacts'] = $parameters['contacts'] ?? [];
                break;

            case 'location':
                $data['location'] = [
                    'latitude' => $parameters['latitude'] ?? '',
                    'longitude' => $parameters['longitude'] ?? '',
                    'name' => $parameters['name'] ?? '',
                    'address' => $parameters['address'] ?? ''
                ];
                break;

            default:
                throw new \InvalidArgumentException("Tipo de mensaje no soportado: $type");
        }

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
