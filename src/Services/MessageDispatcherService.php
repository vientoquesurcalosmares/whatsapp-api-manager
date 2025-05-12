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

/**
 * Servicio para enviar mensajes a través de WhatsApp Business API
 * 
 * Maneja diferentes tipos de mensajes (texto, multimedia, reacciones, ubicación)
 * incluyendo respuestas a mensajes existentes, con registro en base de datos y
 * manejo de archivos multimedia.
 */
class MessageDispatcherService
{
    /**
     * Constructor del servicio
     *
     * @param ApiClient $apiClient Cliente para comunicación con la API de WhatsApp
     */
    public function __construct(
        protected ApiClient $apiClient
    ) {}

    /**
     * Envía un mensaje de texto
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $text Contenido del mensaje
     * @param bool $previewUrl Habilitar vista previa de enlaces
     * @return Message Modelo del mensaje creado
     * @throws WhatsappApiException Si falla el envío por la API
     */
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

    /**
     * Envía un mensaje de texto como respuesta a otro mensaje
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param string $text Contenido del mensaje
     * @param bool $previewUrl Habilitar vista previa de enlaces
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si el mensaje de contexto no existe
     * @throws WhatsappApiException Si falla el envío por la API
     */
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
    
    /**
     * Envía una reacción (emoji) como respuesta a un mensaje
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param string $emoji Emoji a enviar
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si el emoji está vacío o no existe el contexto
     * @throws WhatsappApiException Si falla el envío por la API
     */
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

    /**
     * Envía un mensaje con imagen desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param \SplFileInfo $file Archivo de imagen
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws \RuntimeException Si falla la subida del archivo
     * @throws WhatsappApiException Si falla el envío por la API
     */
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
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'image');

        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel,$mediaInfo['url'], $file->getFilename(), 'images');


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
            'message_id' => $message->message_id,
            'media_type' => 'image',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);

        Log::channel('whatsapp')->info('Mensaje y archivo media creados en base de datos.', [
            'message_id' => $message->message_id,
            'media_file_id' => $mediaFile->media_file_id,
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

    /**
     * Envía un mensaje con imagen como respuesta desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param \SplFileInfo $file Archivo de imagen
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el mensaje de contexto
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyImageMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
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

        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);


        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'image');

        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel,$mediaInfo['url'], $file->getFilename(), 'images');


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
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'image',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);

        Log::channel('whatsapp')->info('Mensaje y archivo media creados en base de datos.', [
            'message_id' => $message->message_id,
            'media_file_id' => $mediaFile->media_file_id,
        ]);

        try {

            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
            ];

            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'image', $parameters, $contextMessage->wa_id);

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

    /**
     * Envía un mensaje con imagen desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $link URL de la imagen
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si la URL no es válida
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendImageMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $link
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de imagen por url.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'link' => $link,
        ]);

        $fullPhoneNumber = $countryCode . $phoneNumber;

        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);

        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
            'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
       
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);

        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'image',
            'message_content' => $link,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);


        Log::channel('whatsapp')->info('Mensaje y link creados en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);

        try {

            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
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

    /**
     * Envía un mensaje con imagen como respuesta desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param string $link URL de la imagen
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el contexto o URL inválida
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyImageMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $link
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de imagen por url.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'link' => $link,
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

        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);

        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
            'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
       
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);

        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'image',
            'message_content' => $link,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);


        Log::channel('whatsapp')->info('Mensaje y link creados en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);

        try {

            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
            ];

            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'image', $parameters, $contextMessageId);

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

    /**
     * Envía un mensaje de audio desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param \SplFileInfo $file Archivo de audio
     * @return Message Modelo del mensaje creado
     * @throws \RuntimeException Si falla la subida del archivo
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendAudioMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        \SplFileInfo $file
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de audio.', [
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
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'audio');

        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'audio');

        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel, $mediaInfo['url'], $file->getFilename(), 'audio');
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'audio',
            'message_content' => null,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'audio',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de audio creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'audio', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de audio por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de audio como respuesta desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param \SplFileInfo $file Archivo de audio
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el mensaje de contexto
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyAudioMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        \SplFileInfo $file
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de audio.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'filePath' => $file->getRealPath(),
            'fileName' => $file->getFilename(),
            'fileType' => $file->getExtension(),
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'audio');

        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel, $mediaInfo['url'], $file->getFilename(), 'audio');

        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'audio',
            'message_content' => null,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'audio',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de audio creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'audio', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de audio por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }
    
    /**
     * Envía un mensaje de audio desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $link URL del audio
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si la URL no es válida
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendAudioMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $link
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de audio por URL.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'link' => $link,
        ]);
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
                'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'audio',
            'message_content' => $link,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de audio creado en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'audio', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de audio por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de audio como respuesta desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param string $link URL del audio
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el contexto o URL inválida
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyAudioMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $link
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de audio por URL.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'link' => $link,
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
                'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'audio',
            'message_content' => $link,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de audio creado en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'audio', $parameters, $contextMessageId);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de audio por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de documento desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param \SplFileInfo $file Archivo de documento
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendDocumentMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        \SplFileInfo $file,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de documento.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'filePath' => $file->getRealPath(),
            'fileName' => $file->getFilename(),
            'fileType' => $file->getExtension(),
            'caption' => $caption,
        ]);

        $fullPhoneNumber = $countryCode . $phoneNumber;

        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);

        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'document');

        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel, $mediaInfo['url'], $file->getFilename(), 'documents');

        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);

        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'document',
            'message_content' => $caption, // Puede ser null
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'document',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);

        Log::channel('whatsapp')->info('Mensaje de documento creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);

        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
                'caption' => $caption,
                'filename' => $file->getFilename(),
            ];

            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'document', $parameters);

            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);

            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de documento por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);

            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de documento como respuesta desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param \SplFileInfo $file Archivo de documento
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el mensaje de contexto
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyDocumentMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        \SplFileInfo $file,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de documento como respuesta.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,
            'filePath' => $file->getRealPath(),
            'fileName' => $file->getFilename(),
            'fileType' => $file->getExtension(),
            'caption' => $caption,
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'document');
    
        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel, $mediaInfo['url'], $file->getFilename(), 'documents');

        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);

    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'document',
            'message_content' => $caption, // Puede ser null
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'audio',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de documento creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
                'caption' => $caption,
                'filename' => $file->getFilename(),
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'document', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de documento por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de documento desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $link URL del documento
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si la URL no es válida
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendDocumentMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $link,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de documento por URL.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'link' => $link,
            'caption' => $caption,
        ]);
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
                'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'document',
            'message_content' => $caption.' '.$link, // Puede ser null
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de documento creado en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
                'caption' => $caption,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'document', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de documento por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de documento como respuesta desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param string $link URL del documento
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el contexto o URL inválida
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyDocumentMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $link,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de documento por URL como respuesta.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,
            'link' => $link,
            'caption' => $caption,
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
                'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'document',
            'message_content' => $caption . ' ' . $link, // Puede ser null
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de documento creado en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
                'caption' => $caption,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'document', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de documento por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de sticker desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param \SplFileInfo $file Archivo de sticker
     * @return Message Modelo del mensaje creado
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendStickerMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        \SplFileInfo $file
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de sticker.', [
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
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'sticker');
    
        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel, $mediaInfo['url'], $file->getFilename(), 'stickers');

        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'sticker',
            'message_content' => null,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'sticker',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de sticker creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'sticker', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de sticker por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de sticker como respuesta desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param \SplFileInfo $file Archivo de sticker
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el mensaje de contexto
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyStickerMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        \SplFileInfo $file
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de sticker como respuesta.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,
            'filePath' => $file->getRealPath(),
            'fileName' => $file->getFilename(),
            'fileType' => $file->getExtension(),
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'sticker');

        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel, $mediaInfo['url'], $file->getFilename(), 'stickers');
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'sticker',
            'message_content' => null,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'audio',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de sticker creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'sticker', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de sticker por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de sticker desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $link URL del sticker
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si la URL no es válida
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyStickerMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $link
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de sticker por URL como respuesta.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,
            'link' => $link,
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
                'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'sticker',
            'message_content' => $link,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de sticker creado en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'sticker', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de sticker por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de video desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param \SplFileInfo $file Archivo de video
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendVideoMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        \SplFileInfo $file,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de video.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'filePath' => $file->getRealPath(),
            'fileName' => $file->getFilename(),
            'fileType' => $file->getExtension(),
            'caption' => $caption,
        ]);
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'video');
    
        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel, $mediaInfo['url'], $file->getFilename(), 'videos');
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'video',
            'message_content' => $caption, // Puede ser null
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'video',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de video creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
                'caption' => $caption,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'video', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de video por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de video como respuesta desde archivo local
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param \SplFileInfo $file Archivo de video
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el mensaje de contexto
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyVideoMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        \SplFileInfo $file,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de video como respuesta.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,
            'filePath' => $file->getRealPath(),
            'fileName' => $file->getFilename(),
            'fileType' => $file->getExtension(),
            'caption' => $caption,
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Subir el archivo y obtener el ID del archivo subido
        $fileId = $this->uploadFile($phoneNumberModel, $file, 'video');
    
        // Obtener la URL del archivo subido desde la API de WhatsApp
        $mediaInfo = $this->retrieveMediaInfo($phoneNumberModel, $fileId);

        // Descargar el archivo desde la URL proporcionada por la API
        $localFilePath = $this->downloadMedia($phoneNumberModel, $mediaInfo['url'], $file->getFilename(), 'videos');

        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'video',
            'message_content' => $caption, // Puede ser null
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);

        // Crear un registro del archivo en el modelo MediaFile
        $mediaFile = MediaFile::create([
            'message_id' => $message->message_id,
            'media_type' => 'video',
            'file_name' => $file->getFilename(),
            'mime_type' => $mediaInfo['mime_type'],
            'sha256' => $mediaInfo['sha256'],
            'url' => $localFilePath,
            'media_id' => $mediaInfo['id'],
            'file_size' => $mediaInfo['file_size'],
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de video creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'id' => $fileId,
                'caption' => $caption,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'video', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de video por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de video desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $link URL del video
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si la URL no es válida
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendVideoMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $link,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de video por URL.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'link' => $link,
            'caption' => $caption,
        ]);
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
                'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'video',
            'message_content' => $caption, // Puede ser null
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de video creado en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
                'caption' => $caption,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'video', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de video por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de video como respuesta desde URL pública
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param string $link URL del video
     * @param string|null $caption Texto descriptivo opcional
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si la URL no es válida o el mensaje de contexto no existe
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyVideoMessageByUrl(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $link,
        ?string $caption = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de video por URL como respuesta.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,
            'link' => $link,
            'caption' => $caption,
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que $link sea una URL válida
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            Log::channel('whatsapp')->error('El enlace proporcionado no es una URL válida.', [
                'link' => $link,
            ]);
            throw new \InvalidArgumentException('El enlace proporcionado no es una URL válida.');
        }
    
        // Resolver el contacto
        $contact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'video',
            'message_content' => $caption, // Puede ser null
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de video creado en base de datos.', [
            'message_id' => $message->message_id,
            'link' => $link,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'link' => $link,
                'caption' => $caption,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'video', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de video por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de contacto
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contactId ID del contacto a enviar
     * @return Message Modelo del mensaje creado
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendContactMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contactId
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de contacto.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contactId' => $contactId,
        ]);
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono del destinatario
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que el contacto a enviar exista
        $contact = Contact::findOrFail($contactId);
    
        // Resolver el contacto del destinatario
        $recipientContact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $recipientContact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'contacts',
            'message_content' => $contact->contact_id,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de contacto creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'contacts' => [
                    [
                        'addresses' => [
                            [
                                'street' => $contact->address,
                                'city' => $contact->city,
                                'state' => $contact->state,
                                'zip' => $contact->zip,
                                'country' => $contact->country,
                                'country_code' => $contact->country_code,
                                'type' => 'HOME',
                            ],
                        ],
                        'birthday' => $contact->birthday,
                        'emails' => [
                            [
                                'email' => $contact->email,
                                'type' => 'WORK',
                            ],
                        ],
                        'name' => [
                            'formatted_name' => $contact->full_name,
                            'first_name' => $contact->first_name,
                            'last_name' => $contact->last_name,
                            'middle_name' => $contact->middle_name,
                            'suffix' => $contact->suffix,
                            'prefix' => $contact->prefix,
                        ],
                        'org' => [
                            'company' => $contact->organization,
                            'department' => $contact->department,
                            'title' => $contact->title,
                        ],
                        'phones' => [
                            [
                                'phone' => $contact->phone_number,
                                'wa_id' => $contact->wa_id,
                                'type' => 'CELL',
                            ],
                        ],
                        'urls' => [
                            [
                                'url' => $contact->url,
                                'type' => 'WORK',
                            ],
                        ],
                    ],
                ],
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'contacts', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de contacto por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de contacto como respuesta
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param string $contactId ID del contacto a enviar
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el mensaje de contexto o el contacto
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyContactMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        string $contactId
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de contacto como respuesta.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,
            'contactId' => $contactId,
        ]);
    
        $fullPhoneNumber = $countryCode . $phoneNumber;
    
        // Validar el número de teléfono del destinatario
        $phoneNumberModel = $this->validatePhoneNumber($phoneNumberId);
    
        // Validar que el contacto a enviar exista
        $contact = Contact::findOrFail($contactId);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
        // Resolver el contacto del destinatario
        $recipientContact = $this->resolveContact($countryCode, $phoneNumber);
    
        // Crear el mensaje en la base de datos
        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumberModel->phone_number_id,
            'contact_id' => $recipientContact->contact_id,
            'message_from' => preg_replace('/[\s+]/', '', $phoneNumberModel->display_phone_number),
            'message_to' => $fullPhoneNumber,
            'message_type' => 'contacts',
            'message_content' => $contact->contact_id,
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de contacto creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'contacts' => [
                    [
                        'addresses' => [
                            [
                                'street' => $contact->address,
                                'city' => $contact->city,
                                'state' => $contact->state,
                                'zip' => $contact->zip,
                                'country' => $contact->country,
                                'country_code' => $contact->country_code,
                                'type' => 'HOME',
                            ],
                        ],
                        'birthday' => $contact->birthday,
                        'emails' => [
                            [
                                'email' => $contact->email,
                                'type' => 'WORK',
                            ],
                        ],
                        'name' => [
                            'formatted_name' => $contact->full_name,
                            'first_name' => $contact->first_name,
                            'last_name' => $contact->last_name,
                            'middle_name' => $contact->middle_name,
                            'suffix' => $contact->suffix,
                            'prefix' => $contact->prefix,
                        ],
                        'org' => [
                            'company' => $contact->organization,
                            'department' => $contact->department,
                            'title' => $contact->title,
                        ],
                        'phones' => [
                            [
                                'phone' => $contact->phone_number,
                                'wa_id' => $contact->wa_id,
                                'type' => 'CELL',
                            ],
                        ],
                        'urls' => [
                            [
                                'url' => $contact->url,
                                'type' => 'WORK',
                            ],
                        ],
                    ],
                ],
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'contacts', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de contacto por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de localización
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param float $latitude Latitud de la ubicación
     * @param float $longitude Longitud de la ubicación
     * @param string|null $name Nombre opcional del lugar
     * @param string|null $address Dirección opcional del lugar
     * @return Message Modelo del mensaje creado
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendLocationMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        float $latitude,
        float $longitude,
        ?string $name = null,
        ?string $address = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de localización.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address,
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
            'message_type' => 'location',
            'message_content' => json_encode([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address,
            ]),
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de localización creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'location', $parameters);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de localización por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Envía un mensaje de localización como respuesta
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @param string $contextMessageId ID del mensaje original (WA)
     * @param float $latitude Latitud de la ubicación
     * @param float $longitude Longitud de la ubicación
     * @param string|null $name Nombre opcional del lugar
     * @param string|null $address Dirección opcional del lugar
     * @return Message Modelo del mensaje creado
     * @throws \InvalidArgumentException Si no existe el mensaje de contexto
     * @throws WhatsappApiException Si falla el envío por la API
     */
    public function sendReplyLocationMessage(
        string $phoneNumberId,
        string $countryCode,
        string $phoneNumber,
        string $contextMessageId,
        float $latitude,
        float $longitude,
        ?string $name = null,
        ?string $address = null
    ): Message {
        Log::channel('whatsapp')->info('Iniciando envío de mensaje de localización como respuesta.', [
            'phoneNumberId' => $phoneNumberId,
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'contextMessageId' => $contextMessageId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address,
        ]);
    
        // Verificar que el mensaje de contexto exista
        $contextMessage = Message::where('wa_id', $contextMessageId)->first();
    
        if (!$contextMessage) {
            Log::channel('whatsapp')->error('El mensaje de contexto no existe en la base de datos.', [
                'contextMessageId' => $contextMessageId,
            ]);
            throw new \InvalidArgumentException('El mensaje de contexto no existe.');
        }
    
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
            'message_type' => 'location',
            'message_content' => json_encode([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address,
            ]),
            'message_method' => 'OUTPUT',
            'status' => MessageStatus::PENDING,
            'message_context_id' => $contextMessage->message_id, // Relación con el mensaje de contexto
        ]);
    
        Log::channel('whatsapp')->info('Mensaje de localización creado en base de datos.', [
            'message_id' => $message->message_id,
        ]);
    
        try {
            // Preparar los parámetros para el envío
            $parameters = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address,
            ];
    
            // Enviar el mensaje a través de la API
            $response = $this->sendViaApi($phoneNumberModel, $fullPhoneNumber, 'location', $parameters, $contextMessage->wa_id);
    
            Log::channel('whatsapp')->info('Respuesta recibida de API WhatsApp.', ['response' => $response]);
    
            // Manejar el éxito del envío
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            Log::channel('whatsapp')->error('Error al enviar mensaje de localización por API WhatsApp.', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'details' => $e->getDetails(),
            ]);
    
            // Manejar el error del envío
            return $this->handleError($message, $e);
        }
    }

    /**
     * Valida el número de teléfono y verifica que tenga un token API válido
     *
     * @param string $phoneNumberId ID del número telefónico registrado
     * @return WhatsappPhoneNumber Modelo del número telefónico
     * @throws \InvalidArgumentException Si el número no tiene un token API válido asociado
     */
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

    /**
     * Resuelve el contacto del destinatario o lo crea si no existe
     *
     * @param string $countryCode Código de país del destinatario
     * @param string $phoneNumber Número de teléfono del destinatario
     * @return Contact Modelo del contacto
     */
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

    /**
     * Envía un mensaje a través de la API de WhatsApp
     *
     * @param WhatsappPhoneNumber $phone Número telefónico registrado
     * @param string $to Número de teléfono del destinatario
     * @param string $type Tipo de mensaje (text, image, video, etc.)
     * @param array $parameters Parámetros específicos del tipo de mensaje
     * @param string|null $contextMessageId ID del mensaje de contexto (opcional)
     * @return array Respuesta de la API
     */
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
                $data['image'] = isset($parameters['id']) && $parameters['id'] 
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

    /**
     * Crea una sesión de subida para archivos grandes
     *
     * @param WhatsappPhoneNumber $phone Número telefónico registrado
     * @param string $fileName Nombre del archivo
     * @param string $fileType Tipo MIME del archivo
     * @param int $fileLength Tamaño del archivo en bytes
     * @return string ID de la sesión de subida
     */
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

    /**
     * Valida el archivo de medios antes de subirlo
     *
     * @param \SplFileInfo $file Archivo a validar
     * @param string $mediaType Tipo de medio (image, video, document, etc.)
     * @throws \RuntimeException Si el archivo no es válido
     */
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

    /**
     * Sube un archivo a la API de WhatsApp
     *
     * @param WhatsappPhoneNumber $phone Número telefónico registrado
     * @param \SplFileInfo $file Archivo a subir
     * @param string $type_file Tipo de archivo (image, video, document, etc.)
     * @return string ID del archivo subido
     * @throws \RuntimeException Si falla la subida del archivo
     */
    private function uploadFile(WhatsappPhoneNumber $phone, \SplFileInfo $file, string $type_file): string
    {
        $endpoint = Endpoints::build(Endpoints::UPLOAD_MEDIA, [
            'phone_number_id' => $phone->api_phone_number_id,
        ]);

        Log::info('Subiendo archivo a la API de WhatsApp.', [
            'endpoint' => $endpoint,
            'filePath' => $file->getRealPath(),
        ]);

        // Validar el archivo antes de subirlo
        $this->validateMediaFile($file, $type_file);

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

    /**
     * Recupera la información de un archivo subido
     *
     * @param WhatsappPhoneNumber $phone Número telefónico registrado
     * @param string $fileId ID del archivo subido
     * @return array Información del archivo
     */
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

    /**
     * Descarga un archivo de medios desde la URL proporcionada
     *
     * @param WhatsappPhoneNumber $phone Número telefónico registrado
     * @param string $url URL del archivo a descargar
     * @param string $fileName Nombre del archivo local
     * @param string $mediaType Tipo de medio (image, video, document, etc.)
     * @return string Ruta local del archivo descargado
     */
    private function downloadMedia(WhatsappPhoneNumber $phone, string $url, string $fileName, string $mediaType): string
    {
        // Obtener la ruta de almacenamiento desde la configuración
        $storagePath = config("whatsapp.media.storage_path.$mediaType");

        if (!$storagePath) {
            throw new \RuntimeException("No se ha configurado una ruta de almacenamiento para el tipo de media: $mediaType");
        }
        
        $localFilePath = storage_path('app/public/media/' . $fileName);
        $directoryPath = dirname($localFilePath);

        // Verificar y crear el directorio si no existe
        if (!is_dir($directoryPath)) {
            Log::info('Creando directorio para guardar el archivo.', ['directoryPath' => $directoryPath]);
            mkdir($directoryPath, 0755, true);
        }

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

    /**
     * Maneja el éxito del envío de un mensaje
     *
     * @param Message $message Modelo del mensaje
     * @param array $response Respuesta de la API
     * @return Message Modelo del mensaje actualizado
     */
    private function handleSuccess(Message $message, array $response): Message
    {
        Log::channel('whatsapp')->info('Mensaje enviado exitosamente.', [
            'message_id' => $message->message_id,
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

    /**
     * Maneja el error del envío de un mensaje
     *
     * @param Message $message Modelo del mensaje
     * @param WhatsappApiException $e Excepción lanzada por la API
     * @return Message Modelo del mensaje actualizado
     */
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
