<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Enums\MessageStatus;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\MediaFile;
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
            'message_content' => $emoji,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);

        Log::channel('whatsapp')->info('Mensaje creado en base de datos.', ['message_id' => $message->message_id]);

        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'message_id' => $contextMessage->wa_id,
                'emoji' => $emoji,
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
    public function sendImageMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        \SplFileInfo $file,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de imagen.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'filePath' => $file->getRealPath(),
            'fileName' => $file->getFilename(),
            'fileType' => $file->getExtension(),
        ]);

        $fullPhoneNumber = $countryCode . $phoneNumber;

        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);


        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file);

        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel,$mediaInfo['url'], $file->getFilename());


        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);

        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'image',
            'message_content' => $caption !== null ? $caption : null,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->id,
            'media_type' => 'image',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);

        Log::channel('whatsapp')->info('Mensaje y archivo media creados en base de datos.', [
            'message_id' => $message->id,
            'media_file_id' => $mediaFile->id,
        ]);

        try {

            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
            ];

            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'image', $parameters);

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

    private function createUploadSession(WhatsappPhoneNumber $phone,string $fileName, string $fileType, int $fileLength): string
    {
        $endpoint = Endpoints::build(Endpoints::CREATE_RESUMABLE_UPLOAD_SESSION, [
            'version' => config('whatsapp.api.version'),
        ]);

        $queryParams = [
            'file_name' => $fileName,
            'file_type' => $fileType,
            'file_length' => $fileLength,
        ];

        Log::info('Creando sesión de subida para archivo.', [
            'endpoint' => $endpoint,
            'queryParams' => $queryParams,
        ]);

        try {
            $response = $this->apiClient->request(
                'POST',
                $endpoint,
                query: $queryParams,
                headers: [
                    'Authorization' => 'Bearer ' . $phone->businessAccount->api_token,
                    'Content-Type' => 'application/json',
                ]
            );
    
            Log::info('Sesión de subida creada exitosamente.', [
                'response' => $response,
            ]);
    
            return $response['id'] ?? throw new \RuntimeException('No se pudo crear la sesión de subida.');
        } catch (\Exception $e) {
            Log::error('Error al crear la sesión de subida.', [
                'error_message' => $e->getMessage(),
                'queryParams' => $queryParams,
            ]);
            throw $e;
        }
    }

    private function validateMediaFile(\SplFileInfo $file, string $mediaType): void
    {
        $maxFileSize = config("whatsapp.media.max_file_size.$mediaType");
        $allowedMimeTypes = config("whatsapp.media.allowed_types.$mediaType");

        // Validar que los tipos MIME permitidos estén configurados
        if (!is_array($allowedMimeTypes)) {
            Log::error('La configuración de tipos MIME permitidos no es válida.', [
                'mediaType' => $mediaType,
                'allowedMimeTypes' => $allowedMimeTypes,
            ]);
            throw new \RuntimeException("La configuración de tipos MIME permitidos para '$mediaType' no es válida.");
        }

        // Validar tamaño del archivo
        if ($file->getSize() > $maxFileSize) {
            Log::error('El archivo excede el tamaño máximo permitido.', [
                'filePath' => $file->getRealPath(),
                'fileSize' => $file->getSize(),
                'maxFileSize' => $maxFileSize,
            ]);
            throw new \RuntimeException('El archivo excede el tamaño máximo permitido.');
        }

        // Validar tipo MIME del archivo
        $fileMimeType = mime_content_type($file->getRealPath());
        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            Log::error('El tipo de archivo no es permitido.', [
                'filePath' => $file->getRealPath(),
                'fileMimeType' => $fileMimeType,
                'allowedMimeTypes' => $allowedMimeTypes,
            ]);
            throw new \RuntimeException('El tipo de archivo no es permitido.');
        }

        Log::info('Archivo validado correctamente.', [
            'filePath' => $file->getRealPath(),
            'fileSize' => $file->getSize(),
            'fileMimeType' => $fileMimeType,
        ]);
    }

    private function uploadFile(WhatsappPhoneNumber $phone, \SplFileInfo $file): string
    {
        $endpoint = Endpoints::build(Endpoints::UPLOAD_MEDIA, [
            'phone_number_id' => $phone->api_phone_number_id,
        ]);

        Log::info('Subiendo archivo a la API de WhatsApp.', [
            'endpoint' => $endpoint,
            'filePath' => $file->getRealPath(),
        ]);

        // Validar el archivo antes de subirlo
        $this->validateMediaFile($file, 'image');

        try {
            // Enviar la solicitud para subir el archivo
            $response = $this->apiClient->request(
                'POST',
                $endpoint,
                headers: [
                    'Authorization' => 'Bearer ' . $phone->businessAccount->api_token,
                ],
                data: [
                    'multipart' => [
                        [
                            'name' => 'messaging_product',
                            'contents' => 'whatsapp',
                        ],
                        [
                            'name' => 'file',
                            'contents' => fopen($file->getRealPath(), 'r'),
                            'filename' => $file->getFilename(),
                            'headers' => [
                                'Content-Type' => mime_content_type($file->getRealPath()),
                            ],
                        ],
                    ],
                ]
            );

            Log::info('Archivo subido exitosamente.', ['response' => $response]);

            // Verificar y devolver el ID del archivo subido
            return $response['id'] ?? throw new \RuntimeException('No se pudo obtener el ID del archivo subido.');
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Capturar y registrar la respuesta de error
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;
            $responseStatusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

            Log::error('Error al subir el archivo.', [
                'error_message' => $e->getMessage(),
                'response_body' => $responseBody,
                'response_status_code' => $responseStatusCode,
                'filePath' => $file->getRealPath(),
            ]);

            throw new \RuntimeException('Error al subir el archivo: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private function retrieveMediaInfo(WhatsappPhoneNumber $phone, string $fileId): array
    {
        $endpoint = Endpoints::build(Endpoints::RETRIEVE_MEDIA_URL, [
            'version' => config('whatsapp.api.version'),
            'media_id' => $fileId,
        ]);

        Log::info('Recuperando información del archivo subido.', ['endpoint' => $endpoint]);

        $response = $this->apiClient->request(
            'GET',
            $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $phone->businessAccount->api_token,
            ]
        );

        return $response;
    }

    private function downloadMedia(WhatsappPhoneNumber $phone, string $url, string $fileName): string
    {
        $localFilePath = storage_path('app/public/media/' . $fileName);

        Log::info('Descargando archivo desde la URL.', ['url' => $url, 'localFilePath' => $localFilePath]);

        $attempts = 3; // Número de reintentos
        $delay = 2; // Segundos entre reintentos

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = $this->apiClient->request(
                    'GET',
                    $url,
                    headers: [
                        'Authorization' => 'Bearer ' . $phone->businessAccount->api_token,
                    ]
                );

                file_put_contents($localFilePath, $response);

                Log::info('Archivo descargado exitosamente.', ['localFilePath' => $localFilePath]);

                return $localFilePath;
            } catch (\Exception $e) {
                Log::error('Error al descargar el archivo.', [
                    'attempt' => $i + 1,
                    'error_message' => $e->getMessage(),
                    'url' => $url,
                ]);

                if ($i < $attempts - 1) {
                    sleep($delay); // Esperar antes de reintentar
                } else {
                    throw new \RuntimeException('Error al descargar el archivo después de varios intentos: ' . $e->getMessage(), $e->getCode(), $e);
                }
            }
        }
        // Si por alguna razón el bucle termina sin lanzar excepción, lanzar una excepción genérica
        throw new \RuntimeException('No se pudo descargar el archivo después de varios intentos.');
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
