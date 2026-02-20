<?php

namespace ScriptDevelop\WhatsappManager\Services\WebhookProcessors;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ScriptDevelop\WhatsappManager\Helpers\CountryCodes;
use ScriptDevelop\WhatsappManager\Helpers\MessagingLimitHelper;
use ScriptDevelop\WhatsappManager\Contracts\WebhookProcessorInterface;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;
use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;

class BaseWebhookProcessor implements WebhookProcessorInterface
{
    // Implementa todos los métodos del controlador actual aquí
    // Mueve la lógica de WhatsappWebhookController a esta clase

    public function handle(Request $request): Response|JsonResponse
    {
        $verifyToken = config('whatsapp.webhook.verify_token', env('WHATSAPP_VERIFY_TOKEN'));

        if ($request->isMethod('get') && $request->has(['hub_mode', 'hub_challenge', 'hub_verify_token'])) {
            return $this->verifyWebhook($request, $verifyToken);
        }

        if ($request->isMethod('post')) {
            return $this->processIncomingMessage($request);
        }
        Log::channel('whatsapp')->error('Registro webhook invalido', [$request]);
        return response()->json(['error' => 'Invalid request method.'], 400);
    }

    public function verifyWebhook(Request $request, string $verifyToken): Response|JsonResponse
    {
        if (
            $request->input('hub_mode') === 'subscribe' &&
            $request->input('hub_verify_token') === $verifyToken
        ) {
            Log::channel('whatsapp')->info('Configuracion de webhook valido!', [$request]);
            return response()->make($request->input('hub_challenge'), 200);
        }
        Log::channel('whatsapp')->error('Error al registrar webhook!', [$request]);
        return response()->json(['error' => 'Invalid verify token.'], 403);
    }

    public function processIncomingMessage(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::channel('whatsapp')->info('Received WhatsApp Webhook Payload:', $payload);

        $change = data_get($payload, 'entry.0.changes.0');
        $value = $change['value'] ?? null;
        $field = $change['field'] ?? null;

        Log::channel('whatsapp')->info('Processing webhook field:', ['field' => $field]);

        switch ($field) {
            case 'history':
                $this->handleHistorySync($value);
                return response()->json(['success' => true]);

            case 'smb_app_state_sync':
                $this->handleSmbAppStateSync($value);
                return response()->json(['success' => true]);

            case 'smb_message_echoes':
                $this->handleSmbMessageEchoes($value);
                return response()->json(['success' => true]);

            case 'account_update':
                $this->handleAccountUpdate($value);
                return response()->json(['success' => true]);

            case 'business_capability_update':
                $this->handleBusinessCapabilityUpdate($value, $payload);
                return response()->json(['success' => true]);
        }

        if ($field === 'message_template' or $field=== 'message_template_status_update') {
            $this->handleTemplateEvent($value);
            return response()->json(['success' => true]);
        }

        if ($field === 'user_preferences') {
            $this->handleUserPreferences($value);
            return response()->json(['success' => true]);
        }

        if (!$value) {
            Log::channel('whatsapp')->warning('No value found in webhook payload.', $payload);
            return response()->json(['error' => 'Invalid payload.'], 422);
        }

        // Si es evento de estado de plantilla
        if ($field === 'message_template_status_update' && isset($value['statuses'][0])) {
            $this->handleTemplateStatusUpdate($value['statuses'][0]);
        }

        // Si es mensaje normal
        elseif (isset($value['messages'][0])) {
            $this->handleIncomingMessage(
                $value['messages'][0] ?? [],
                $value['contacts'][0] ?? null,
                $value['metadata'] ?? null
            );
        }

        // Si es status de mensaje (entregado, leído, etc.)
        elseif (isset($value['statuses'][0])) {
            $this->handleStatusUpdate($value['statuses'][0]);
        }

        return response()->json(['success' => true]);
    }

    protected function handleIncomingMessage(array $message, ?array $contact, ?array $metadata): void
    {
        $messageType = $message['type'] ?? '';

        $messageRecord = null;

        $textContent = null;

        Log::channel('whatsapp')->warning('Handle Incoming Message: ', [
            'message_type' => $messageType,
            'contact' => $contact,
            'metadata' => $metadata,
        ]);

        if (empty($contact['wa_id'])) {
            Log::channel('whatsapp')->warning('No wa_id found in contact.', $contact ?? []);
            return;
        }

        $fullPhone = preg_replace('/\D/', '', $message['from'] ?? '');

        [$countryCode, $phoneNumber] = $this->splitPhoneNumber($fullPhone);

        if (empty($countryCode) || empty($phoneNumber)) {
            Log::channel('whatsapp')->warning('Unable to split phone number.', ['fullPhone' => $fullPhone]);
            return;
        }
        $contactName = $contact['profile']['name'] ?? null;

        Log::channel('whatsapp')->info('CONTACT Processing message from contact.', [
            'wa_id' => $contact['wa_id'],
            'country_code' => $countryCode,
            'phone_number' => $phoneNumber,
            'contact_name' => $contactName,
            'raw_profile' => $contact['profile'] ?? null,
        ]);

        // Usar firstOrCreate
        $contactRecord = WhatsappModelResolver::contact()->firstOrCreate(
            [
                'country_code' => $countryCode,
                'phone_number' => $phoneNumber,
            ],
            [
                'wa_id' => $contact['wa_id'],
                'contact_name' => $contactName,
            ]
        );

        Log::channel('whatsapp')->info('CONTACT After firstOrCreate.', [
            'contact_id' => $contactRecord->contact_id,
            'wa_id' => $contactRecord->wa_id,
            'contact_name' => $contactRecord->contact_name,
            'was_created' => $contactRecord->wasRecentlyCreated,
            'attributes' => $contactRecord->getAttributes(),
        ]);


        // Actualizar el contacto con los datos más recientes
        if ($contactRecord->wa_id !== $contact['wa_id'] || $contactRecord->contact_name !== $contactName) {
            // Intentar actualización con Query Builder (sin Eloquent)
            Log::channel('whatsapp')->info('CONTACT Trying Query Builder update.', [
                'contact_name_value' => $contactName,
            ]);

            $contactRecord->update([
                'wa_id' => $contact['wa_id'],
                'contact_name' => $contactName,
            ]);

            // $updateResult = \DB::table('whatsapp_contacts')
            //     ->where('contact_id', $contactRecord->contact_id)
            //     ->update([
            //         'wa_id' => $contact['wa_id'],
            //         'contact_name' => $contactName,
            //         'updated_at' => now(),
            //     ]);

            // Log::channel('whatsapp')->info('CONTACT Query Builder update result.', [
            //     'rows_affected' => $updateResult,
            // ]);

            // Recargar el modelo
            $contactRecord->refresh();
        }

        $apiPhoneNumberId = $metadata['phone_number_id'] ?? null;

        $whatsappPhone = null;
        if ($apiPhoneNumberId) {
            $whatsappPhone = WhatsappModelResolver::phone_number()->where('api_phone_number_id', $apiPhoneNumberId)->first();
        }

        // Manejar mensajes de sistema
        if (!$whatsappPhone) {
            Log::channel('whatsapp')->error('No matching WhatsappPhoneNumber found for api_phone_number_id.', [
                'api_phone_number_id' => $apiPhoneNumberId,
            ]);
            return;
        }

        // Manejar mensajes de sistema
        if ($messageType === 'system') {
            $messageRecord = $this->processSystemMessage($message, $contactRecord, $whatsappPhone);
            $this->fireSystemMessageReceived($contactRecord, $messageRecord);
            return;
        }

        // Manejar mensajes de texto
        if ($messageType === 'text') {
            $messageRecord = $this->processTextMessage($message, $contactRecord, $whatsappPhone);

            $this->fireTextMessageReceived($contactRecord, $messageRecord);
        }

        // Manejar mensajes interactivos (botones, listas)
        if ($messageType === 'interactive') {
            $messageRecord = $this->processInteractiveMessage($message, $contactRecord, $whatsappPhone);

            $this->fireInteractiveMessageReceived($contactRecord, $messageRecord);
        }

        if ($messageType === 'location') {
            $messageRecord = $this->processLocationMessage($message, $contactRecord, $whatsappPhone);

            $this->fireLocationMessageReceived($contactRecord, $messageRecord);
        }

        if ($messageType === 'contacts') {
            $messageRecord = $this->processContactMessage($message, $contactRecord, $whatsappPhone);

            $this->fireContactMessageReceived($contactRecord, $messageRecord);
        }

        if ($messageType === 'reaction') {
            $messageRecord = $this->processReactionMessage($message, $contactRecord, $whatsappPhone);

            $this->fireReactionReceived($contactRecord, $messageRecord);
        }

        // Manejar mensajes de media
        if (in_array($messageType, ['image', 'audio', 'video', 'document', 'sticker'])) {
            $messageRecord =  $this->processMediaMessage($message, $contactRecord, $whatsappPhone);

            $this->fireMediaMessageReceived($contactRecord, $messageRecord);
        }

        // Manejar mensajes no soportados
        if ($messageType === 'unsupported') {
            $title_error = $message['errors'][0]['title'] ?? 'Unsupported message type';
            $message_error = $message['errors'][0]['message'] ?? 'This message type is not supported by the current implementation.';
            $error_details = $message['errors'][0]['error_data']['details'] ?? 'No additional details available';
            $messageRecord = $this->processUnsupportedMessage($message, $contactRecord, $whatsappPhone);
            $this->fireUnsupportedMessageReceived($contactRecord, $messageRecord, $title_error, $message_error, $error_details);
        }

        $logMessage = $textContent ?? ($message['text']['body'] ?? $message['type'] . ' content not available');

        $this->fireMessageReceived($contactRecord, $messageRecord);

        Log::channel('whatsapp')->info('Incoming message processed.', [
            'message_id' => $message['id'],
            'contact_id' => $contactRecord->contact_id,
            'phone_number' => $fullPhone,
            'message_type' => $messageType,
            'content' => $logMessage,
        ]);
    }

    private function getMessageContentForType(string $messageType, array $message): ?string
    {
        switch ($messageType) {
            case 'text':
                return $message['text']['body'] ?? null;
            case 'interactive':
                return $message['interactive']['button_reply']['title']
                    ?? $message['interactive']['list_reply']['title']
                    ?? null;
            case 'location':
                return "Location: " . ($message['location']['name'] ?? '');
            case 'contacts':
                return "Shared contacts: " . count($message['contacts'] ?? []);
            case 'reaction':
                return $message['reaction']['emoji'] ?? null;
            default:
                return $message[$messageType]['caption'] ?? strtoupper($messageType);
        }
    }

    protected function processTextMessage(array $message, Model $contact, Model $whatsappPhone): ?Model
    {
        $textContent = $message['text']['body'] ?? null;

        Log::channel('whatsapp')->info('Processing text message.', [
            'message' => $message,
            'contact' => $contact,
            'whatsappPhone' => $whatsappPhone,
            'textContent' => $textContent,
        ]);

        if (!$textContent) {
            Log::channel('whatsapp')->warning('No text content found in message.', $message);
            return null;
        }

        $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
            [
                'wa_id' => $message['id'],
            ],
            [
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contact->contact_id,
                'conversation_id' => null, // Esto se puede actualizar más tarde si es necesario
                'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
                'message_from' => preg_replace('/[\D+]/', '', $message['from']),
                'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
                'message_type' => 'TEXT',
                'message_content' => $textContent,
                'json_content' => json_encode($message),
                'status' => 'received',
                'message_context_id' => $this->getContextMessageId($message),
            ]);

        Log::channel('whatsapp')->info('Text message processed and saved.', [
            'message_id' => $messageRecord->message_id,
            'wa_id' => $message['id'],
            'content' => $textContent,
        ]);

        return $messageRecord;
    }

    protected function processInteractiveMessage(array $message, Model $contact, Model $whatsappPhone): ?Model
    {
        $interactiveType = $message['interactive']['type'] ?? '';
        $textContent = null;

        if ($interactiveType === 'button_reply') {
            $textContent = $message['interactive']['button_reply']['title'] ?? null;
        } else if ($interactiveType === 'list_reply') {
            $textContent = $message['interactive']['list_reply']['title'] ?? null;
        }

        if (!$textContent) {
            Log::channel('whatsapp')->warning('No se pudo extraer contenido del mensaje interactivo.', $message);
            return null;
        }

        // Guardar el mensaje en la base de datos como tipo INTERACTIVE
        $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
            [
                'wa_id' => $message['id'],
            ],
            [
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contact->contact_id,
                'conversation_id' => null,
                'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
                'message_from' => preg_replace('/[\D+]/', '', $message['from']),
                'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
                'message_type' => strtoupper($message['type']),
                'message_content' => $textContent,
                'json_content' => json_encode($message),
                'status' => 'received',
                'message_context_id' => $this->getContextMessageId($message),
            ]);

        Log::channel('whatsapp')->info('Mensaje interactivo procesado y guardado.', [
            'message_id' => $messageRecord->message_id,
            'wa_id' => $message['id'],
            'content' => $textContent,
        ]);

        return $messageRecord;
    }

    protected function processMediaMessage(array $message, Model $contact, Model $whatsappPhone): Model
    {
        $mediaId = $message[$message['type']]['id'] ?? null;
        $caption = $message[$message['type']]['caption'] ?? strtoupper($message['type']);
        $mimeType = $message[$message['type']]['mime_type'] ?? null;

        Log::channel('whatsapp')->info('Processing media message.', [
            'message' => $message,
            'contact' => $contact,
            'whatsappPhone' => $whatsappPhone,
            'mediaId' => $mediaId,
            'caption' => $caption,
            'mimeType' => $mimeType,
        ]);

        if (!$mediaId) {
            Log::channel('whatsapp')->warning('No media ID found in message.', $message);
            throw new \RuntimeException('No media ID found in message.');
        }

        $mediaUrl = $this->getMediaUrl($mediaId, $whatsappPhone);

        if (!$mediaUrl) {
            Log::channel('whatsapp')->error('Failed to retrieve media URL.', ['media_id' => $mediaId]);
            throw new \RuntimeException('Failed to retrieve media URL.');
        }

        $mediaContent = $this->downloadMedia($mediaUrl, $whatsappPhone);

        if (!$mediaContent) {
            Log::channel('whatsapp')->error('Failed to download media content.', ['media_url' => $mediaUrl]);
            throw new \RuntimeException('Failed to download media content.');
        }

        // Obtener el tipo de media pluralizado según la configuración
        $mediaType = $message['type']. 's'; // Por defecto pluralizar el tipo de media

        // Obtener la ruta de almacenamiento configurada desde la config
        $directory = config("whatsapp.media.storage_path.$mediaType");

        if (!$directory) {
            throw new \RuntimeException("No se ha configurado una ruta de almacenamiento para el tipo de media: $mediaType");
        }

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = $this->getFileExtension($mimeType);

        // Si es un archivo de audio y tiene extensión .bin, forzar a .ogg
        if ($message['type'] === 'audio' && $extension === 'bin') {
            $extension = 'ogg';
        }

        if ($message['type'] === 'sticker' && $extension === 'bin') {
            $extension = 'webp';
        }

        $fileName = "{$mediaId}.{$extension}";
        if( Str::endsWith($directory, '/')) {
            $directory = rtrim($directory, '/');
        }
        $filePath = "{$directory}/{$fileName}";
        file_put_contents($filePath, $mediaContent);

        // Convertir el path absoluto a relativo para Storage::url
        $relativePath = str_replace(storage_path('app/public/'), '', $directory . '/' . $fileName);

        // Obtener la URL pública
        $publicPath = Storage::url($relativePath);

        // Crear el registro del mensaje en la base de datos
        $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
            [
                'wa_id' => $message['id'],
            ],
            [
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contact->contact_id,
                'conversation_id' => null, // Esto se puede actualizar más tarde si es necesario
                'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
                'message_from' => preg_replace('/[\D+]/', '', $message['from']),
                'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
                'message_type' => strtoupper($message['type']),
                'message_content' => $caption,
                'caption' => $caption,
                'json_content' => json_encode($message),
                'status' => 'received',
                'message_context_id' => $this->getContextMessageId($message),
            ]);

        // Actualizar ó Crear el registro del archivo multimedia en la base de datos
        WhatsappModelResolver::media_file()->updateOrCreate(
            [
                'message_id' => $messageRecord->message_id,
                'media_id' => $mediaId,
            ],
            [
                'media_type' => $message['type'],
                'file_name' => $fileName,
                'url' => $publicPath,
                'mime_type' => $mimeType,
                'sha256' => $message[$message['type']]['sha256'] ?? null,
            ]);

        Log::channel('whatsapp')->info('Media file and message saved.', [
            'message_id' => $messageRecord->message_id,
            'file_path' => $filePath,
            'public_url' => $publicPath,
        ]);

        //Cargar los datos de archivos multimedia, para que también se transmita en los eventos correspondientes
        $messageRecord->loadMissing([
            'mediaFiles',
        ]);

        return $messageRecord;
    }

    protected function processLocationMessage(array $message, Model $contact, Model $whatsappPhone): ?Model
    {
        $location = $message['location'] ?? null;

        if (!$location) {
            Log::channel('whatsapp')->warning('No location data found in message.', $message);
            return null;
        }

        $content = "Ubicación: " . ($location['name'] ?? '') . " - " . ($location['address'] ?? '');
        $coordinates = "Lat: {$location['latitude']}, Lon: {$location['longitude']}";

        $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
            [
                'wa_id' => $message['id'],
            ],
            [
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contact->contact_id,
                'conversation_id' => null,
                'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
                'message_from' => preg_replace('/[\D+]/', '', $message['from']),
                'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
                'message_type' => 'LOCATION',
                'message_content' => $content . ' | ' . $coordinates,
                'json_content' => json_encode($message),
                'status' => 'received',
                'message_context_id' => $this->getContextMessageId($message),
            ]);

        Log::channel('whatsapp')->info('Location message processed.', [
            'message_id' => $messageRecord->message_id,
            'location' => $location
        ]);

        return $messageRecord;
    }

    protected function processContactMessage(array $message, Model $contact, Model $whatsappPhone): ?Model
    {
        $contactData = $message['contacts'][0] ?? null;

        if (!$contactData) {
            Log::channel('whatsapp')->warning('No contact data found in message.', $message);
            return null;
        }

        $name = $contactData['name']['formatted_name'] ?? 'Nombre no disponible';
        $phones = collect($contactData['phones'] ?? [])->pluck('phone')->implode(', ');
        $emails = collect($contactData['emails'] ?? [])->pluck('email')->implode(', ');

        $content = "Nombre: {$name} | Teléfonos: {$phones} | Correos: {$emails}";

        $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
            [
                'wa_id' => $message['id'],
            ],
            [
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contact->contact_id,
                'conversation_id' => null,
                'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
                'message_from' => preg_replace('/[\D+]/', '', $message['from']),
                'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
                'message_type' => 'CONTACT',
                'message_content' => $content,
                'json_content' => json_encode($message),
                'status' => 'received',
                'message_context_id' => $this->getContextMessageId($message),
            ]);

        Log::channel('whatsapp')->info('Contact shared message processed.', [
            'message_id' => $messageRecord->message_id,
            'contact' => $content
        ]);

        return $messageRecord;
    }

    protected function processReactionMessage(array $message, Model $contact, Model $whatsappPhone): ?Model
    {
        $reaction = $message['reaction'] ?? null;

        if (!$reaction || !isset($reaction['emoji'], $reaction['message_id'])) {
            Log::channel('whatsapp')->warning('Reacción inválida o incompleta.', $message);
            return null;
        }

        // Opcional: guardar la reacción asociada al mensaje original
        $originalMessage = WhatsappModelResolver::message()->where('wa_id', $reaction['message_id'])->first();

        $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
            [
                'wa_id' => $message['id'],
            ],
            [
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contact->contact_id,
                'conversation_id' => $originalMessage?->conversation_id,
                'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
                'message_from' => preg_replace('/[\D+]/', '', $message['from']),
                'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
                'message_type' => 'REACTION',
                'message_content' => $reaction['emoji'],
                'json_content' => json_encode($message),
                'status' => 'received',
                'message_context_id' => $reaction['message_id'],
            ]);

        Log::channel('whatsapp')->info('Reacción procesada.', [
            'message_id' => $messageRecord->message_id,
            'reaction' => $reaction
        ]);

        return $messageRecord;
    }

    /**
     * Si un mensaje es una respuesta a otro mensaje, se busca el message_id del mensaje
     * @param array $message
     * @return string|null
     */
    protected function getContextMessageId(array $message): ?string
    {
        if( isset($message['context']) and isset($message['context']['id']) )
        {
            // Si el mensaje tiene contexto, buscar ese id en la base de datos
            $context_message = WhatsappModelResolver::message()
                ->select('message_id')
                ->where('wa_id', '=', $message['context']['id'])
                ->first();

            if( $context_message ){
                return $context_message->message_id;
            }
        }
        return null;
    }

    private function getMediaUrl(string $mediaId, Model $whatsappPhone): ?string
    {
        $url = config('whatsapp.api.base_url', env('WHATSAPP_API_URL')) . '/' . config('whatsapp.api.version', env('WHATSAPP_API_VERSION')) . "/$mediaId?phone_number_id=" . $whatsappPhone->api_phone_number_id;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $whatsappPhone->businessAccount->api_token,
        ])->get($url);

        return $response->json()['url'] ?? null;
    }

    private function downloadMedia(string $url, Model $whatsappPhone): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $whatsappPhone->businessAccount->api_token,
        ])->get($url);

        return $response->successful() ? $response->body() : null;
    }

    private function getFileExtension(?string $mimeType): string
    {
        //Prevenir que el mimetype sea parecido a esto: "audio/ogg; codecs=opus", así son las notas de voz
        if ($mimeType && str_contains($mimeType, ';')) {
            $mimeType = explode(';', $mimeType)[0];
        }

        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'audio/ogg', 'audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr' => 'ogg',
            'video/mp4', 'video/3gp' => 'mp4',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'image/webp' => 'webp',
            default => function() use ($mimeType) {
                Log::channel('whatsapp')->warning("Extensión desconocida para MIME type: {$mimeType}");
                return 'bin';
            },
        };
    }

    protected function handleStatusUpdate(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;
        $timestamp = $status['timestamp'] ?? null;

        if (empty($messageId) || empty($statusValue)) {
            Log::channel('whatsapp')->warning('Missing message ID or status in status update.', $status);
            return;
        }

        $messageRecord = WhatsappModelResolver::message()->where('wa_id', $messageId)->first();

        if (!$messageRecord) {
            Log::channel('whatsapp')->warning('Message record not found for status update.', ['wa_id' => $messageId]);
            return;
        }

        // 1. Actualizar estado del mensaje
        $messageUpdated = $this->updateMessageStatus($messageRecord, $status);

        switch ($statusValue) {
            case 'delivered': $this->fireMessageDelivered($messageUpdated);
                break;

            case 'read': $this->fireMessageRead($messageUpdated);
                break;

            case 'failed':
                // Manejar caso específico de opt-out de marketing
                if (isset($status['errors'][0]['code']) && $status['errors'][0]['code'] == 131050) {
                    $this->fireMarketingOptOut($messageUpdated);
                } else {
                    $this->fireMessageFailed($messageUpdated);
                }
                break;
        }

        // 2. Procesar datos de conversación y métricas
        if (isset($status['conversation'])) {
            $this->processConversationData($messageRecord, $status);
        }

        Log::channel('whatsapp')->info('Estado actualizado', [
            'message_id' => $messageRecord->message_id,
            'wa_id' => $messageId,
            'status' => $statusValue,
            'conversation' => $messageRecord->conversation_id
        ]);
    }

    private function splitPhoneNumber(string $fullPhone): array
    {
        $codes = CountryCodes::codes();

        usort($codes, static fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($fullPhone, $code)) {
                $phoneNumber = substr($fullPhone, strlen($code));

                $phoneNumber = CountryCodes::normalizeInternationalPhone($code, $phoneNumber)['phoneNumber'];
                return [$code, $phoneNumber];
            }
        }

        return [null, null];
    }

    private function updateMessageStatus(Model $message, array $status): Model
    {
        $statusValue = $status['status'] ?? null;
        $timestamp = $status['timestamp'] ?? null;
        $errorCode = $status['errors'][0]['code'] ?? null;

        $updateData = ['status' => $statusValue];

        if ($timestamp) {
            $date = \Carbon\Carbon::createFromTimestamp($timestamp);

            match($statusValue) {
                'delivered' => $updateData['delivered_at'] = $date,
                'read' => $updateData['read_at'] = $date,
                'failed' => $updateData['failed_at'] = $date,
                default => null
            };
        }

        if ($statusValue == 'failed' && $errorCode == 131050) {
            $updateData['is_marketing_opt_out'] = true;
            $this->updateContactMarketingPreference($message->contact_id, false);
        }

        //Si falló el mensaje, guardar los datos del error
        if( $statusValue=='failed' && isset($status['errors']) && isset($status['errors'][0]) ){
            $updateData['code_error'] = (integer)$status['errors'][0]['code'];
            $updateData['title_error'] = $status['errors'][0]['title'];
            $updateData['message_error'] = $status['errors'][0]['message'];

            if( isset($status['errors'][0]['error_data']) ){
                if( isset($status['errors'][0]['error_data']['details']) ){
                    $updateData['details_error'] = $status['errors'][0]['error_data']['details'];
                }
                else{
                    $updateData['details_error'] = $status['errors'][0]['error_data'];
                }
            }
        }

        $message->update($updateData);

        return $message;
    }

    private function processConversationData(Model $message, array $status): void
    {
        $conversationData = $status['conversation'];
        $pricingData = $status['pricing'] ?? [];

        // Validar expiration_timestamp
        $expirationTimestamp = null;
        if (isset($conversationData['expiration_timestamp'])
            && is_numeric($conversationData['expiration_timestamp'])) {
            $expirationTimestamp = \Carbon\Carbon::createFromTimestamp(
                $conversationData['expiration_timestamp']
            );
        }

        // Validar ID de conversación
        if (empty($conversationData['id'])) {
            Log::channel('whatsapp')->warning('Conversation ID inválido', $conversationData);
            return;
        }

        $conversation = WhatsappModelResolver::conversation()->updateOrCreate(
            ['wa_conversation_id' => $conversationData['id']],
            [
                'expiration_timestamp' => $expirationTimestamp,
                'origin' => $conversationData['origin']['type'] ?? 'unknown',
                'pricing_model' => $pricingData['pricing_model'] ?? null,
                'billable' => $pricingData['billable'] ?? false,
                'category' => $pricingData['category'] ?? 'service'
            ]
        );

        // Vincular conversación al mensaje si no está asignada
        if (!$message->conversation_id) {
            $message->conversation()->associate($conversation);
            $message->save();
        }
    }

    private function validateResponse(string $userInput, ?array $validationConfig): \Illuminate\Contracts\Validation\Validator
    {
        // Si no hay configuración de validación, retornar un validador vacío
        if (!$validationConfig || empty($validationConfig['rules'])) {
            return \Illuminate\Support\Facades\Validator::make(
                ['input' => $userInput],
                []
            );
        }

        // Crear el array de datos para validación usando un nombre de campo genérico
        $data = ['input' => $userInput];

        // Obtener las reglas de validación
        $rules = ['input' => $validationConfig['rules']];

        // Crear mensajes personalizados si existen
        $customMessages = [];
        if (isset($validationConfig['retryMessage'])) {
            $customMessages = [
                'input.required' => $validationConfig['retryMessage'],
                'input.in' => $validationConfig['retryMessage'],
                'input.*' => $validationConfig['retryMessage']  // Mensaje genérico para todas las reglas
            ];
        }

        // Crear y retornar el validador
        return \Illuminate\Support\Facades\Validator::make($data, $rules, $customMessages);
    }

    private function getMessageContent($message): string
    {
        if ($message->message_type === 'text') {
            return $message->content;
        }

        // Para mensajes interactivos, usa el cuerpo principal
        $data = json_decode($message->content, true);
        return $data['body'] ?? 'Mensaje no disponible';
    }

    private function sendInteractiveResponse($message, Model $contact, Model $phoneNumber): void
    {
        $service = app(MessageDispatcherService::class);
        $data = json_decode($message->content, true);

        if ($message->message_type === 'interactive_buttons') {
            // $service->sendButtonMessage(
            @$service->sendInteractiveButtonsMessage(
                $phoneNumber->phone_number_id,
                $contact->country_code,
                $contact->phone_number,
                $data['body'],
                $data['buttons'],
                $data['footer'] ?? null
            );
        } elseif ($message->message_type === 'interactive_list') {
            $service->sendListMessage(
                $phoneNumber->phone_number_id,
                $contact->country_code,
                $contact->phone_number,
                $data['body'],
                $data['sections'],
                $data['button'] ?? 'Ver opciones',
                $data['footer'] ?? null
            );
        }
    }

    protected function processUnsupportedMessage(array $message, Model $contact, Model $whatsappPhone): ?Model
    {
        $errorCode = $message['errors'][0]['code'] ?? null;
        $errorTitle = $message['errors'][0]['title'] ?? 'Unsupported content';
        $errorDetails = $message['errors'][0]['error_data']['details'] ?? 'Unknown error';

        $content = "Unsupported message. Error: $errorCode - $errorTitle: $errorDetails";

        $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
            [
                'wa_id' => $message['id'],
            ],
            [
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contact->contact_id,
                'conversation_id' => null,
                'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
                'message_from' => preg_replace('/[\D+]/', '', $message['from']),
                'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
                'message_type' => 'UNSUPPORTED',
                'message_content' => $content,
                'json_content' => json_encode($message),
                'status' => 'received',
                'code_error' => $errorCode,
                'title_error' => $errorTitle,
                'details_error' => $errorDetails,
                'message_context_id' => $this->getContextMessageId($message),
            ]
        );

        Log::channel('whatsapp')->warning('Unsupported message processed', [
            'message_id' => $messageRecord->message_id,
            'error_code' => $errorCode,
            'error_details' => $errorDetails
        ]);

        return $messageRecord;
    }

    protected function handleTemplateEvent(array $templateData): void
    {
        $event = $templateData['event'] ?? null;
        $templateId = $templateData['id'] ?? null;
        if( empty($templateId) ) {
            $templateId = $templateData['message_template_id'] ?? null;
        }

        if (!$event || !$templateId) {
            Log::channel('whatsapp')->warning('Invalid template event payload.', $templateData);
            return;
        }

        switch ($event) {
            case 'APPROVED':
            case 'REJECTED':
            case 'PENDING':
                $this->handleTemplateStatusUpdate($templateData);
                break;
            case 'CREATE':
                $this->handleTemplateCreation($templateData);
                break;
            case 'UPDATE':
                $this->handleTemplateUpdate($templateData);
                break;
            case 'PENDING_DELETION':
            case 'DELETE':
                $this->handleTemplateDeletion($templateData);
                break;
            case 'DISABLE':
                $this->handleTemplateDisable($templateData);
                break;
            default:
                Log::channel('whatsapp')->warning("Unhandled template event: {$event}", $templateData);
        }
    }

    protected function handleTemplateStatusUpdate(array $templateData): void
    {
        $templateId = $templateData['id'] ?? null;
        if( empty($templateId) ) {
            $templateId = $templateData['message_template_id'] ?? null;
        }
        $newStatus = $templateData['event'] ?? null; // APPROVED, REJECTED, PENDING
        $reason = $templateData['reason'] ?? null;
        $components = $templateData['components'] ?? [];

        if (!$templateId || !$newStatus) {
            Log::channel('whatsapp')->warning('Invalid template status update payload.', $templateData);
            return;
        }

        $template = WhatsappModelResolver::template()
            ->where('wa_template_id', $templateId)
            ->first();

        if (!$template) {
            Log::channel('whatsapp')->warning("Template not found: {$templateId}");
            return;
        }

        // Actualizar plantilla principal
        $template->update([
            'status' => $newStatus,
            //'rejection_reason' => $reason //No existe el cmapo rejection_reason en este modelo, solo en las versiones
        ]);

        // Crear nueva versión si hay cambios
        if (!empty($components)) {
            // Hay cambios en la estructura -> nueva versión
            $this->createTemplateVersion($template, $templateData);
        } else {
            // No hay cambios -> actualizar última versión existente
            $lastVersion = $template->versions()->latest()->first();
            if ($lastVersion) {
                $lastVersion->update([
                    'status' => $newStatus,
                    'rejection_reason' => $reason,
                    'is_active' => ($templateData['event'] === 'APPROVED'),
                ]);
            } else {
                // Caso excepcional: no hay versiones pero debemos crear una
                $this->createTemplateVersion($template, array_merge($templateData, [
                    'components' => $template->components->toArray() ?? []
                ]));
            }
        }

        Log::channel('whatsapp')->info("Template status updated: {$newStatus}", [
            'template_id' => $template->template_id,
            'reason' => $reason
        ]);
    }

    protected function createTemplateVersion(Model $template, array $templateData): void
    {
        $components = $templateData['components'] ?? $template->components->toArray();
        $event = $templateData['event'] ?? null;
        $reason = $templateData['reason'] ?? null;

        // Calcular hash del contenido
        $contentHash = md5(json_encode($components));

        // Verificar si ya existe una versión con este hash
        $existingVersion = $template->versions()
            ->where('version_hash', $contentHash)
            ->first();

        if ($existingVersion) {
            // Actualizar versión existente
            $existingVersion->update([
                'status' => $event,
                'rejection_reason' => $reason
            ]);
            return;
        }

        // Crear nueva versión
        $version = WhatsappModelResolver::template_version()->create([
            'template_id' => $template->template_id,
            'version_hash' => $contentHash,
            'template_structure' => json_encode($components),
            'status' => $event,
            'rejection_reason' => $reason
        ]);

        Log::channel('whatsapp')->info('New template version created', [
            'template_id' => $template->template_id,
            'version_id' => $version->version_id
        ]);
    }

    protected function handleTemplateCreation(array $templateData): void
    {
        $templateId = $templateData['id'] ?? null;
        if( empty($templateId) ) {
            $templateId = $templateData['message_template_id'] ?? null;
        }
        $businessAccountId = $templateData['business_account_id'] ?? null;
        $name = $templateData['name'] ?? null;
        $language = $templateData['language'] ?? null;
        $components = $templateData['components'] ?? [];
        $category = $templateData['category'] ?? null;
        $status = $templateData['status'] ?? 'PENDING';

        if (!$templateId || !$businessAccountId || !$name || !$language) {
            Log::channel('whatsapp')->warning('Missing required fields for template creation.', $templateData);
            return;
        }

        $businessAccount = WhatsappModelResolver::business_account()
            ->where('whatsapp_business_id', $businessAccountId)
            ->first();

        if (!$businessAccount) {
            Log::channel('whatsapp')->warning("Business account not found: {$businessAccountId}");
            return;
        }

        // Crear plantilla principal
        $template = WhatsappModelResolver::template()->create([
            'wa_template_id' => $templateId,
            'whatsapp_business_id' => $businessAccount->whatsapp_business_id,
            'name' => $name,
            'language' => $language,
            'status' => $status,
            'category_id' => $this->resolveCategoryId($category),
            'json' => json_encode($templateData)
        ]);

        // Crear versión inicial
        $this->createTemplateVersion($template, array_merge($templateData, ['event' => 'CREATE']));

        Log::channel('whatsapp')->info("Template created: {$name}", [
            'template_id' => $template->template_id,
            'status' => $status
        ]);
    }

    protected function handleTemplateUpdate(array $templateData): void
    {
        $templateId = $templateData['id'] ?? null;
        if( empty($templateId) ) {
            $templateId = $templateData['message_template_id'] ?? null;
        }
        $components = $templateData['components'] ?? [];

        if (!$templateId) {
            Log::channel('whatsapp')->warning('Missing template ID for update', $templateData);
            return;
        }

        $template = WhatsappModelResolver::template()
            ->where('wa_template_id', $templateId)
            ->first();

        if (!$template) {
            Log::channel('whatsapp')->warning("Template not found for update: {$templateId}");
            return;
        }

        // Actualizar plantilla principal
        $template->update([
            'name' => $templateData['name'] ?? $template->name,
            'language' => $templateData['language'] ?? $template->language,
            'category_id' => $this->resolveCategoryId($templateData['category'] ?? null),
            'json' => json_encode($templateData)
        ]);

        // Crear nueva versión
        $this->createTemplateVersion($template, $templateData);

        Log::channel('whatsapp')->info("Template updated: {$template->name}", [
            'template_id' => $template->template_id
        ]);
    }

    protected function handleTemplateDeletion(array $templateData): void
    {
        $templateId = $templateData['id'] ?? null;
        if( empty($templateId) ) {
            $templateId = $templateData['message_template_id'] ?? null;
        }

        if (!$templateId) {
            Log::channel('whatsapp')->warning('Missing template ID for deletion', $templateData);
            return;
        }

        $template = WhatsappModelResolver::template()
            ->where('wa_template_id', $templateId)
            ->first();

        if (!$template) {
            Log::channel('whatsapp')->warning("Template not found for deletion: {$templateId}");
            return;
        }

        // Soft delete de la plantilla y sus versiones
        $template->delete();
        $template->versions()->delete();

        Log::channel('whatsapp')->info("Template deleted: {$template->name}", [
            'template_id' => $template->template_id
        ]);
    }

    protected function handleTemplateDisable(array $templateData): void
    {
        $templateId = $templateData['id'] ?? null;
        if( empty($templateId) ) {
            $templateId = $templateData['message_template_id'] ?? null;
        }

        if (!$templateId) {
            Log::channel('whatsapp')->warning('Missing template ID for disable', $templateData);
            return;
        }

        $template = WhatsappModelResolver::template()
            ->where('wa_template_id', $templateId)
            ->first();

        if (!$template) {
            Log::channel('whatsapp')->warning("Template not found for disable: {$templateId}");
            return;
        }

        // Desactivar plantilla y versiones
        $template->update(['status' => 'DISABLED']);
        $template->versions()->update(['status' => 'DISABLED']);

        Log::channel('whatsapp')->info("Template disabled: {$template->name}", [
            'template_id' => $template->template_id
        ]);
    }

    protected function resolveCategoryId(?string $categoryName): ?string
    {
        if (!$categoryName) return null;

        $category = WhatsappModelResolver::template_category()
            ->where('name', $categoryName)
            ->first();

        return $category ? $category->category_id : null;
    }


    /**
     * Ahora el disparo de los eventos estáran en métodos, y se usarán las clases de los eventos configuradas en el archivo de configuración whatsapp.events!
     */

    protected function fireTextMessageReceived($contactRecord, $messageRecord){
        $event = config('whatsapp.events.messages.text.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
        ]));
    }

    protected function fireInteractiveMessageReceived($contactRecord, $messageRecord){
        $event = config('whatsapp.events.messages.interactive.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
        ]));
    }

    protected function fireUnsupportedMessageReceived($contactRecord, $messageRecord, $titleError, $messageError, $detailsError)
    {
        $event = config('whatsapp.events.messages.unsupported.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
            'title_error' => $titleError,
            'message_error' => $messageError,
            'details_error' => $detailsError
        ]));
    }

    protected function fireLocationMessageReceived($contactRecord, $messageRecord){
        $event = config('whatsapp.events.messages.location.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
        ]));
    }

    protected function fireContactMessageReceived($contactRecord, $messageRecord){
        $event = config('whatsapp.events.messages.contact.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
        ]));
    }

    protected function fireReactionReceived($contactRecord, $messageRecord){
        $event = config('whatsapp.events.messages.reaction.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
        ]));
    }

    protected function fireMediaMessageReceived($contactRecord, $messageRecord){
        $event = config('whatsapp.events.messages.media.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
        ]));
    }

    protected function fireMessageReceived($contactRecord, $messageRecord){
        $event = config('whatsapp.events.messages.message.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
        ]));
    }

    protected function fireMessageDelivered($messageUpdated){
        $event = config('whatsapp.events.messages.message.delivered');
        event(new $event([
            'message' => $messageUpdated,
        ]));
    }

    protected function fireMessageRead($messageUpdated){
        $event = config('whatsapp.events.messages.message.read');
        event(new $event([
            'message' => $messageUpdated,
        ]));
    }

    protected function fireMessageFailed($messageUpdated){
        $event = config('whatsapp.events.messages.message.failed');
        event(new $event([
            'message' => $messageUpdated,
        ]));
    }

    protected function fireSystemMessageReceived($contactRecord, $messageRecord)
    {
        $event = config('whatsapp.events.messages.system.received');
        event(new $event([
            'contact' => $contactRecord,
            'message' => $messageRecord,
        ]));
    }

    protected function fireMarketingOptOut($messageUpdated)
    {
        $event = config('whatsapp.events.messages.message.marketing_opt_out');
        event(new $event([
            'message' => $messageUpdated,
        ]));
    }

    protected function processSystemMessage(array $message, Model $contact, Model $whatsappPhone): ?Model
    {
        $systemType = $message['system']['type'] ?? '';
        $body = $message['system']['body'] ?? '';
        $newWaId = $message['system']['new_wa_id'] ?? null;

        // Caso especial: cambio de número
        if ($systemType === 'user_changed_number') {
            return $this->processUserChangedNumber($message, $contact, $whatsappPhone, $body, $newWaId);
        }

        if ($systemType === 'user_preference_changed') {
            $marketingPreference = $message['system']['marketing'] ?? null;
            if ($marketingPreference) {
                $this->updateContactMarketingPreference(
                    $contact->contact_id,
                    $marketingPreference === 'resume'
                );
            }
        }

        // Otros tipos de mensajes de sistema
        $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
            ['wa_id' => $message['id']],
            [
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contact->contact_id,
                'conversation_id' => null,
                'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
                'message_from' => preg_replace('/[\D+]/', '', $message['from']),
                'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
                'message_type' => 'SYSTEM',
                'message_content' => $body,
                'json_content' => json_encode($message),
                'status' => 'received',
                'message_context_id' => $this->getContextMessageId($message),
            ]
        );

        Log::channel('whatsapp')->info('System message processed', [
            'message_id' => $messageRecord->message_id,
            'system_type' => $systemType
        ]);

        return $messageRecord;
    }

    protected function processUserChangedNumber(
        array $message,
        Model $contact,
        Model $whatsappPhone,
        string $body,
        ?string $newWaId
    ): ?Model
    {
        // Actualizar el contacto con el nuevo wa_id
        if ($newWaId) {
            $contact->update(['wa_id' => $newWaId]);

            Log::channel('whatsapp')->info('Contact phone number updated', [
                'contact_id' => $contact->contact_id,
                'old_wa_id' => $contact->getOriginal('wa_id'),
                'new_wa_id' => $newWaId
            ]);
        }

        // Crear registro del mensaje
        $messageRecord = WhatsappModelResolver::message()->create([
            'wa_id' => $message['id'],
            'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
            'contact_id' => $contact->contact_id,
            'conversation_id' => null,
            'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
            'message_from' => preg_replace('/[\D+]/', '', $message['from']),
            'message_to' => preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number),
            'message_type' => 'SYSTEM',
            'message_content' => $body,
            'json_content' => json_encode($message),
            'status' => 'received',
            'message_context_id' => $this->getContextMessageId($message),
        ]);

        return $messageRecord;
    }

    protected function updateContactMarketingPreference($contactId, $acceptsMarketing): void
    {
        $contact = WhatsappModelResolver::contact()->find($contactId);

        if ($contact) {
            $updateData = ['accepts_marketing' => $acceptsMarketing];

            // Cuando se establece en false (opt-out), registrar la marca de tiempo
            if ($acceptsMarketing === false) {
                $updateData['marketing_opt_out_at'] = now();
            } else {
                // Si se establece en true, limpiar la marca de tiempo
                $updateData['marketing_opt_out_at'] = null;
            }

            $contact->update($updateData);

            Log::channel('whatsapp')->info('Contact marketing preference updated', [
                'contact_id' => $contactId,
                'accepts_marketing' => $acceptsMarketing
            ]);
        }
    }

    protected function handleUserPreferences(array $data): void
    {
        $userPreferences = $data['user_preferences'] ?? [];

        foreach ($userPreferences as $preference) {
            if ($preference['category'] === 'marketing_messages') {
                $waId = $preference['wa_id'];
                $value = $preference['value']; // 'stop' o 'resume'

                $this->updateContactMarketingPreferenceByWaId($waId, $value);
            }
        }
    }

    protected function updateContactMarketingPreferenceByWaId(string $waId, string $preference): void
    {
        $contact = WhatsappModelResolver::contact()->where('wa_id', $waId)->first();

        if ($contact) {
            $acceptsMarketing = ($preference === 'resume');
            $this->updateContactMarketingPreference($contact->contact_id, $acceptsMarketing);
        }
    }


    // =========================================================================
    // COEXISTENCE WEBHOOKS IMPLEMENTATION
    // =========================================================================





    /**
     * Maneja la sincronización del historial de mensajes de coexistencia
     */
    /**
     * Webhook para sincronización de historial de mensajes
     * Se dispara cuando un negocio completa el onboarding con coexistencia
     * y comparte su historial de mensajes
     */
    protected function handleHistorySync(array $data): void
    {
        Log::channel('whatsapp')->info('🔄 [COEXISTENCE] Iniciando sincronización de historial', [
            'phone_number_id' => $data['metadata']['phone_number_id'] ?? null,
            'display_phone_number' => $data['metadata']['display_phone_number'] ?? null
        ]);

        $messagingProduct = $data['messaging_product'] ?? 'whatsapp';
        $metadata = $data['metadata'] ?? [];
        $historyData = $data['history'] ?? [];

        $phoneNumberId = $metadata['phone_number_id'] ?? null;
        $displayPhoneNumber = $metadata['display_phone_number'] ?? null;

        if (!$phoneNumberId) {
            Log::channel('whatsapp')->warning('No phone_number_id found in history sync', $data);
            return;
        }

        $whatsappPhone = WhatsappModelResolver::phone_number()
            ->where('api_phone_number_id', $phoneNumberId)
            ->first();

        if (!$whatsappPhone) {
            Log::channel('whatsapp')->warning('No matching phone number found for history sync', [
                'phone_number_id' => $phoneNumberId
            ]);
            return;
        }

        // Verificar si hay errores (cuando el negocio no comparte historial)
        if (isset($historyData['errors'])) {
            $this->handleHistorySyncError($historyData['errors'], $whatsappPhone);
            return;
        }

        // Procesar threads de historial
        foreach ($historyData as $historyItem) {
            $this->processHistoryThreads($historyItem, $whatsappPhone);
        }

        // Post-procesamiento personalizado
        $this->afterHistorySync($data);

        Log::channel('whatsapp')->info('✅ [COEXISTENCE] Historial sincronizado exitosamente', [
            'phone_number_id' => $phoneNumberId,
            'threads_count' => count($historyData)
        ]);
    }

    /**
     * Procesa los threads de historial de mensajes
     */
    protected function processHistoryThreads(array $historyItem, Model $whatsappPhone): void
    {
        $metadata = $historyItem['metadata'] ?? [];
        $threads = $historyItem['threads'] ?? [];

        $phase = $metadata['phase'] ?? 0;
        $chunkOrder = $metadata['chunk_order'] ?? 1;
        $progress = $metadata['progress'] ?? 0;

        Log::channel('whatsapp')->info('Processing history chunk', [
            'phase' => $phase,
            'chunk_order' => $chunkOrder,
            'progress' => $progress,
            'threads_count' => count($threads)
        ]);

        foreach ($threads as $thread) {
            $this->processHistoryThread($thread, $whatsappPhone, $phase);
        }
    }

    /**
     * Procesa un thread individual del historial
     */
    protected function processHistoryThread(array $thread, Model $whatsappPhone, int $phase): void
    {
        $threadId = $thread['id'] ?? null; // WhatsApp user phone number
        $messages = $thread['messages'] ?? [];

        if (!$threadId || empty($messages)) {
            return;
        }

        // Obtener o crear el contacto
        $fullPhone = preg_replace('/\D/', '', $threadId);
        [$countryCode, $phoneNumber] = $this->splitPhoneNumber($fullPhone);

        if (empty($countryCode) || empty($phoneNumber)) {
            Log::channel('whatsapp')->warning('Unable to split phone number in history thread', [
                'thread_id' => $threadId
            ]);
            return;
        }

        $contactRecord = WhatsappModelResolver::contact()->firstOrCreate(
            [
                'country_code' => $countryCode,
                'phone_number' => $phoneNumber,
            ],
            [
                'wa_id' => $threadId,
                'contact_name' => null, // No hay nombre en el historial
            ]
        );

        // Procesar mensajes del thread
        foreach ($messages as $message) {
            $this->processHistoryMessage($message, $contactRecord, $whatsappPhone, $phase);
        }
    }

    /**
     * Procesa un mensaje individual del historial
     */
    protected function processHistoryMessage(array $message, Model $contact, Model $whatsappPhone, int $phase): void
    {
        // Lógica personalizada pre-procesamiento
        $this->beforeProcessHistoryMessage($message, $contact, $whatsappPhone, $phase);

        $messageType = $message['type'] ?? '';
        $from = $message['from'] ?? '';
        $timestamp = $message['timestamp'] ?? null;
        $historyContext = $message['history_context'] ?? [];

        // Normalizar números para comparación
        $fromNormalized = preg_replace('/[\D+]/', '', $from);
        $displayPhoneNormalized = preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number);

        // Determinar si el mensaje fue enviado por el negocio (OUTPUT) o recibido (INPUT)
        $isFromBusiness = ($fromNormalized === $displayPhoneNormalized);

        // Preparar datos base del mensaje
        $messageData = [
            'wa_id' => $message['id'],
            'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
            'contact_id' => $contact->contact_id,
            'conversation_id' => null,
            'messaging_product' => 'whatsapp',
            'message_method' => $isFromBusiness ? 'OUTPUT' : 'INPUT', // Determinar dirección del mensaje
            'message_from' => $fromNormalized,
            'message_to' => $isFromBusiness ?
                preg_replace('/[\D+]/', '', $contact->country_code . $contact->phone_number) :
                $displayPhoneNormalized,
            'message_type' => strtoupper($messageType),
            'json_content' => json_encode($message),
            'status' => $historyContext['status'] ?? 'delivered', // Asumir entregado para historial
            'message_context_id' => $this->getContextMessageId($message),
            'is_historical' => true, // Marcar como mensaje histórico
            'historical_phase' => $phase,
        ];

        // Procesar contenido según el tipo de mensaje
        switch ($messageType) {
            case 'text':
                $messageData['message_content'] = $message['text']['body'] ?? '';
                break;

            case 'image':
            case 'audio':
            case 'video':
            case 'document':
            case 'sticker':
                $messageData['message_content'] = $message[$messageType]['caption'] ?? strtoupper($messageType);
                break;

            case 'interactive':
                $messageData['message_content'] = $message['interactive']['button_reply']['title']
                    ?? $message['interactive']['list_reply']['title']
                    ?? 'Interactive message';
                break;

            case 'location':
                $messageData['message_content'] = "Location: " . ($message['location']['name'] ?? '');
                break;

            default:
                $messageData['message_content'] = $this->getMessageContentForType($messageType, $message)
                    ?? 'Historical message';
        }

        Log::channel('whatsapp')->info('💾 [COEXISTENCE] Guardando mensaje histórico', [
            'wa_id' => $messageData['wa_id'],
            'type' => $messageData['message_type'],
            'method' => $messageData['message_method'],
            'is_from_business' => $isFromBusiness,
            'phase' => $phase
        ]);

        try {
            // Crear registro del mensaje histórico
            $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
                ['wa_id' => $messageData['wa_id']],
                $messageData
            );

            Log::channel('whatsapp')->debug('✅ [COEXISTENCE] Mensaje histórico guardado', [
                'message_id' => $messageRecord->message_id,
                'type' => $messageType,
                'method' => $messageRecord->message_method,
                'phase' => $phase
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('❌ [COEXISTENCE] Error al guardar mensaje histórico', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_data' => $messageData
            ]);
            throw $e;
        }

        // Lógica personalizada post-procesamiento
        $this->afterProcessHistoryMessage($message, $contact, $whatsappPhone, $phase);
    }

    /**
     * Maneja errores en la sincronización del historial
     */
    protected function handleHistorySyncError(array $errors, Model $whatsappPhone): void
    {
        foreach ($errors as $error) {
            $errorCode = $error['code'] ?? null;
            $errorMessage = $error['message'] ?? 'Unknown error';

            if ($errorCode === 2593109) {
                Log::channel('whatsapp')->warning('Business chose not to share messaging history', [
                    'phone_number_id' => $whatsappPhone->api_phone_number_id,
                    'error' => $errorMessage
                ]);
            } else {
                Log::channel('whatsapp')->error('History sync error', [
                    'phone_number_id' => $whatsappPhone->api_phone_number_id,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage
                ]);
            }
        }
    }

    /**
     * Webhook para sincronización de contactos desde WhatsApp Business App
     * Se dispara cuando hay cambios en los contactos de la app móvil
     */
    protected function handleSmbAppStateSync(array $data): void
    {
        Log::channel('whatsapp')->info('📇 [COEXISTENCE] Sincronizando contactos desde WhatsApp Business App', [
            'phone_number_id' => $data['metadata']['phone_number_id'] ?? null,
            'contacts_count' => count($data['state_sync'] ?? [])
        ]);

        $messagingProduct = $data['messaging_product'] ?? 'whatsapp';
        $metadata = $data['metadata'] ?? [];
        $stateSync = $data['state_sync'] ?? [];

        $phoneNumberId = $metadata['phone_number_id'] ?? null;

        if (!$phoneNumberId) {
            Log::channel('whatsapp')->warning('No phone_number_id found in SMB app state sync', $data);
            return;
        }

        $whatsappPhone = WhatsappModelResolver::phone_number()
            ->where('api_phone_number_id', $phoneNumberId)
            ->first();

        if (!$whatsappPhone) {
            Log::channel('whatsapp')->warning('No matching phone number found for SMB app state sync', [
                'phone_number_id' => $phoneNumberId
            ]);
            return;
        }

        foreach ($stateSync as $syncItem) {
            $this->processContactSync($syncItem, $whatsappPhone);
        }

        // Post-procesamiento personalizado
        $this->afterContactsSync($data);

        Log::channel('whatsapp')->info('✅ [COEXISTENCE] Contactos sincronizados exitosamente', [
            'phone_number_id' => $phoneNumberId,
            'contacts_count' => count($stateSync)
        ]);
    }

    /**
     * Procesa la sincronización de un contacto individual
     */
    protected function processContactSync(array $syncItem, Model $whatsappPhone): void
    {
        // Validación personalizada antes de sincronizar
        if (!$this->shouldSyncContact($syncItem)) {
            Log::channel('whatsapp')->debug('[COEXISTENCE] Contacto omitido por validación personalizada', $syncItem);
            return;
        }

        $type = $syncItem['type'] ?? '';
        $action = $syncItem['action'] ?? '';
        $contactData = $syncItem['contact'] ?? [];
        $metadata = $syncItem['metadata'] ?? [];

        if ($type !== 'contact' || empty($contactData)) {
            return;
        }

        $fullName = $contactData['full_name'] ?? '';
        $firstName = $contactData['first_name'] ?? '';
        $phoneNumberRaw = $contactData['phone_number'] ?? '';

        if (!$phoneNumberRaw) {
            Log::channel('whatsapp')->warning('No phone number in contact sync', $syncItem);
            return;
        }

        // Normalizar número de teléfono
        $fullPhone = preg_replace('/\D/', '', $phoneNumberRaw);
        [$countryCode, $phoneNumber] = $this->splitPhoneNumber($fullPhone);

        if (empty($countryCode) || empty($phoneNumber)) {
            Log::channel('whatsapp')->warning('Unable to split phone number in contact sync', [
                'phone_number_raw' => $phoneNumberRaw
            ]);
            return;
        }

        $waId = $fullPhone; // Usar el número normalizado como WA ID

        if ($action === 'remove') {
            // Marcar contacto como eliminado (soft delete)
            $contact = WhatsappModelResolver::contact()
                ->where('country_code', $countryCode)
                ->where('phone_number', $phoneNumber)
                ->first();

            if ($contact) {
                $contact->delete();
                Log::channel('whatsapp')->info('Contact removed via SMB sync', [
                    'contact_id' => $contact->contact_id
                ]);
            }
        } else {
            // Agregar o actualizar contacto
            $contact = WhatsappModelResolver::contact()->updateOrCreate(
                [
                    'country_code' => $countryCode,
                    'phone_number' => $phoneNumber,
                ],
                [
                    'wa_id' => $waId,
                    'contact_name' => $fullName ?: $firstName,
                    'first_name' => $firstName,
                    'full_name' => $fullName,
                ]
            );

            Log::channel('whatsapp')->debug('Contact synced via SMB app', [
                'contact_id' => $contact->contact_id,
                'action' => $action
            ]);
        }

        // Post-procesamiento personalizado
        $this->afterProcessContactSync($syncItem, $whatsappPhone);
    }

    /**
     * Webhook para ecos de mensajes enviados desde WhatsApp Business App
     * Se dispara en tiempo real cuando el negocio envía mensajes desde la app móvil
     * Esto permite mantener tu aplicación sincronizada con los mensajes enviados desde el móvil
     */
    protected function handleSmbMessageEchoes(array $data): void
    {
        Log::channel('whatsapp')->info('💬 [COEXISTENCE] Eco de mensaje desde WhatsApp Business App recibido', [
            'phone_number_id' => $data['metadata']['phone_number_id'] ?? null,
            'display_phone_number' => $data['metadata']['display_phone_number'] ?? null,
            'messages_count' => count($data['message_echoes'] ?? [])
        ]);

        $messagingProduct = $data['messaging_product'] ?? 'whatsapp';
        $metadata = $data['metadata'] ?? [];
        $messageEchoes = $data['message_echoes'] ?? [];

        $phoneNumberId = $metadata['phone_number_id'] ?? null;
        $displayPhoneNumber = $metadata['display_phone_number'] ?? null;

        if (!$phoneNumberId) {
            Log::channel('whatsapp')->warning('No phone_number_id found in SMB message echoes', $data);
            return;
        }

        $whatsappPhone = WhatsappModelResolver::phone_number()
            ->where('api_phone_number_id', $phoneNumberId)
            ->first();

        if (!$whatsappPhone) {
            Log::channel('whatsapp')->warning('No matching phone number found for SMB message echoes', [
                'phone_number_id' => $phoneNumberId
            ]);
            return;
        }

        // Verificar que existan message_echoes
        if (empty($messageEchoes)) {
            Log::channel('whatsapp')->warning('⚠️ [COEXISTENCE] No hay message_echoes en el payload', [
                'data_keys' => array_keys($data)
            ]);
            return;
        }

        // Log detallado de cada mensaje
        foreach ($messageEchoes as $index => $echo) {
            $messageType = $echo['type'] ?? 'unknown';
            $hasErrors = !empty($echo['errors']);

            Log::channel('whatsapp')->debug("📨 [COEXISTENCE] Mensaje echo {$index}", [
                'type' => $messageType,
                'has_errors' => $hasErrors,
                'error_count' => $hasErrors ? count($echo['errors']) : 0,
                'from' => $echo['from'] ?? null,
                'to' => $echo['to'] ?? null
            ]);

            if ($hasErrors) {
                Log::channel('whatsapp')->warning("⚠️ [COEXISTENCE] Mensaje echo con errores", [
                    'type' => $messageType,
                    'errors' => $echo['errors']
                ]);
            }
        }

        foreach ($messageEchoes as $echo) {
            $this->processSmbMessageEcho($echo, $whatsappPhone);
        }

        // Post-procesamiento personalizado
        $this->afterSmbMessageEchoes($data);

        Log::channel('whatsapp')->info('✅ [COEXISTENCE] Ecos de mensajes procesados exitosamente', [
            'phone_number_id' => $phoneNumberId,
            'echoes_count' => count($messageEchoes)
        ]);
    }



    /**
     * Procesa un eco de mensaje SMB individual
     */
    protected function processSmbMessageEcho(array $echo, Model $whatsappPhone): void
    {
        // Validar que el echo tenga la estructura básica requerida
        if (empty($echo['id']) || empty($echo['from']) || empty($echo['to'])) {
            Log::channel('whatsapp')->warning('⚠️ [COEXISTENCE] Echo de mensaje con estructura incompleta', [
                'echo_keys' => array_keys($echo),
                'has_id' => !empty($echo['id']),
                'has_from' => !empty($echo['from']),
                'has_to' => !empty($echo['to'])
            ]);
            return;
        }

        $from = $echo['from'] ?? '';
        $to = $echo['to'] ?? '';
        $messageType = $echo['type'] ?? 'unknown';
        $timestamp = $echo['timestamp'] ?? null;
        $hasErrors = !empty($echo['errors']);

        Log::channel('whatsapp')->info('🔄 [COEXISTENCE] Procesando eco de mensaje individual', [
            'echo_id' => $echo['id'],
            'from' => $from,
            'to' => $to,
            'type' => $messageType,
            'has_errors' => $hasErrors,
            'whatsapp_phone_display' => $whatsappPhone->display_phone_number ?? null,
            'whatsapp_phone_id' => $whatsappPhone->phone_number_id ?? null
        ]);

        if ($hasErrors) {
            Log::channel('whatsapp')->warning('⚠️ [COEXISTENCE] Echo de mensaje con errores', [
                'echo_id' => $echo['id'],
                'errors' => $echo['errors']
            ]);
        }

        // Pre-procesamiento personalizado
        $this->beforeProcessSmbMessageEcho($echo, $whatsappPhone);

        // CORREGIR PROBLEMA: Normalizar números antes de comparar
        $fromNormalized = preg_replace('/[\D+]/', '', $from);
        $displayPhoneNormalized = preg_replace('/[\D+]/', '', $whatsappPhone->display_phone_number);

        Log::channel('whatsapp')->info('📞 [COEXISTENCE] Comparando números normalizados', [
            'from_original' => $from,
            'from_normalized' => $fromNormalized,
            'display_original' => $whatsappPhone->display_phone_number,
            'display_normalized' => $displayPhoneNormalized,
            'match' => $fromNormalized === $displayPhoneNormalized
        ]);

        // Verificar que el mensaje viene del negocio correcto (usando números normalizados)
        if ($fromNormalized !== $displayPhoneNormalized) {
            Log::channel('whatsapp')->warning('⚠️ [COEXISTENCE] SMB message echo from unexpected number', [
                'expected' => $whatsappPhone->display_phone_number,
                'expected_normalized' => $displayPhoneNormalized,
                'actual' => $from,
                'actual_normalized' => $fromNormalized
            ]);
            return;
        }

        // Obtener o crear el contacto destino
        $fullPhone = preg_replace('/\D/', '', $to);
        [$countryCode, $phoneNumber] = $this->splitPhoneNumber($fullPhone);

        if (empty($countryCode) || empty($phoneNumber)) {
            Log::channel('whatsapp')->warning('⚠️ [COEXISTENCE] Unable to split phone number in SMB message echo', [
                'to' => $to
            ]);
            return;
        }

        Log::channel('whatsapp')->info('👤 [COEXISTENCE] Creando/obteniendo contacto', [
            'country_code' => $countryCode,
            'phone_number' => $phoneNumber,
            'full_phone' => $fullPhone
        ]);

        $contactRecord = WhatsappModelResolver::contact()->firstOrCreate(
            [
                'country_code' => $countryCode,
                'phone_number' => $phoneNumber,
            ],
            [
                'wa_id' => $to,
                'contact_name' => null,
            ]
        );

        // DETERMINAR SI ES MENSAJE MULTIMEDIA
        $isMediaMessage = in_array($messageType, ['image', 'audio', 'video', 'document', 'sticker']);

        // Si es mensaje multimedia, usar el método especializado
        if ($isMediaMessage) {
            $this->processSmbMediaMessageEcho($echo, $contactRecord, $whatsappPhone, $messageType);
        } else {
            // Para mensajes no multimedia, usar el procesamiento normal
            $this->processSmbNonMediaMessageEcho($echo, $contactRecord, $whatsappPhone, $messageType);
        }

        // Post-procesamiento personalizado
        $this->afterProcessSmbMessageEcho($echo, $whatsappPhone);
    }

    /**
     * Maneja actualizaciones de cuenta (útil para detectar desconexiones)
     */
    protected function handleAccountUpdate(array $data): void
    {
        Log::channel('whatsapp')->info('🔄 [ACCOUNT] Processing account update', $data);

        $event = $data['event'] ?? '';
        $wabaInfo = $data['waba_info'] ?? [];

        Log::channel('whatsapp')->info('📋 [ACCOUNT] Account update details', [
            'event' => $event,
            'waba_info' => $wabaInfo
        ]);

        switch ($event) {
            case 'PARTNER_APP_INSTALLED':
                $this->handlePartnerAppInstalled($wabaInfo);
                break;

            case 'PARTNER_ADDED':
                $this->handlePartnerAdded($wabaInfo);
                break;

            case 'PARTNER_APP_UNINSTALLED':
                $this->handlePartnerAppUninstalled($wabaInfo);
                break;

            case 'PARTNER_REMOVED':
                $this->handlePartnerRemoved($wabaInfo);
                break;

            default:
                Log::channel('whatsapp')->warning('⚠️ [ACCOUNT] Unhandled account update event', [
                    'event' => $event,
                    'waba_info' => $wabaInfo
                ]);
        }

        // Disparar evento de actualización de cuenta
        $this->fireAccountUpdate($data);
    }

    /**
     * Maneja la instalación de la aplicación partner
     */
    protected function handlePartnerAppInstalled(array $wabaInfo): void
    {
        $wabaId = $wabaInfo['waba_id'] ?? null;
        $ownerBusinessId = $wabaInfo['owner_business_id'] ?? null;
        $partnerAppId = $wabaInfo['partner_app_id'] ?? null;

        Log::channel('whatsapp')->info('✅ [ACCOUNT] Partner app installed', [
            'waba_id' => $wabaId,
            'owner_business_id' => $ownerBusinessId,
            'partner_app_id' => $partnerAppId
        ]);

        // Buscar la cuenta de WhatsApp Business afectada
        $businessAccount = WhatsappModelResolver::business_account()
            ->where('whatsapp_business_id', $wabaId)
            ->first();

        if (!$businessAccount) {
            Log::channel('whatsapp')->error('❌ [ACCOUNT] Business account not found for app install', [
                'waba_id' => $wabaId
            ]);
            return;
        }

        // Actualizar el estado de la cuenta de negocio
        $businessAccount->update([
            'status' => 'active',
            'partner_app_id' => $partnerAppId,
            'disconnected_at' => null,
            'fully_removed_at' => null,
            'disconnection_reason' => null,
        ]);

        // Actualizar todos los números de teléfono asociados
        $phoneNumbers = $businessAccount->phoneNumbers ?? collect();
        $phoneNumbers->each(function($phone) {
            $phone->update([
                'status' => 'active',
                'disconnected_at' => null,
                'fully_removed_at' => null,
                'disconnection_reason' => null,
            ]);
        });

        Log::channel('whatsapp')->info('✅ [ACCOUNT] Business account marked as active', [
            'business_account_id' => $businessAccount->whatsapp_business_id,
            'waba_id' => $wabaId,
            'phone_numbers_affected' => $phoneNumbers->count()
        ]);

        // Disparar eventos
        $this->firePartnerAppInstalled($businessAccount, $wabaInfo);
        $this->fireAccountStatusUpdated($businessAccount, 'active');
    }

    /**
     * Maneja la adición de un partner
     */
    protected function handlePartnerAdded(array $wabaInfo): void
    {
        $wabaId = $wabaInfo['waba_id'] ?? null;
        $ownerBusinessId = $wabaInfo['owner_business_id'] ?? null;

        Log::channel('whatsapp')->info('✅ [ACCOUNT] Partner added', [
            'waba_id' => $wabaId,
            'owner_business_id' => $ownerBusinessId
        ]);

        // Buscar la cuenta de WhatsApp Business afectada
        $businessAccount = WhatsappModelResolver::business_account()
            ->where('whatsapp_business_id', $wabaId)
            ->first();

        if (!$businessAccount) {
            Log::channel('whatsapp')->error('❌ [ACCOUNT] Business account not found for partner added', [
                'waba_id' => $wabaId
            ]);
            return;
        }

        // Actualizar el estado de la cuenta de negocio
        $businessAccount->update([
            'status' => 'active',
            'disconnected_at' => null,
            'fully_removed_at' => null,
            'disconnection_reason' => null,
        ]);

        // Actualizar todos los números de teléfono asociados
        $phoneNumbers = $businessAccount->phoneNumbers ?? collect();
        $phoneNumbers->each(function($phone) {
            $phone->update([
                'status' => 'active',
                'disconnected_at' => null,
                'fully_removed_at' => null,
                'disconnection_reason' => null,
            ]);
        });

        Log::channel('whatsapp')->info('✅ [ACCOUNT] Business account marked as active after partner added', [
            'business_account_id' => $businessAccount->whatsapp_business_id,
            'waba_id' => $wabaId,
            'phone_numbers_affected' => $phoneNumbers->count()
        ]);

        // Disparar eventos
        $this->firePartnerAdded($businessAccount, $wabaInfo);
        $this->fireAccountStatusUpdated($businessAccount, 'active');
    }

    /**
     * Maneja la desinstalación de la aplicación partner
     */
    protected function handlePartnerAppUninstalled(array $wabaInfo): void
    {
        $wabaId = $wabaInfo['waba_id'] ?? null;
        $ownerBusinessId = $wabaInfo['owner_business_id'] ?? null;
        $partnerAppId = $wabaInfo['partner_app_id'] ?? null;

        Log::channel('whatsapp')->warning('🚫 [ACCOUNT] Partner app uninstalled', [
            'waba_id' => $wabaId,
            'owner_business_id' => $ownerBusinessId,
            'partner_app_id' => $partnerAppId
        ]);

        // Buscar la cuenta de WhatsApp Business afectada
        $businessAccount = WhatsappModelResolver::business_account()
            ->where('whatsapp_business_id', $wabaId)
            ->first();

        if (!$businessAccount) {
            Log::channel('whatsapp')->error('❌ [ACCOUNT] Business account not found for uninstall', [
                'waba_id' => $wabaId
            ]);
            return;
        }

        // Actualizar el estado de la cuenta de negocio
        $businessAccount->update([
            'status' => 'disconnected',
            'partner_app_id' => null,
            'disconnected_at' => now(),
            'disconnection_reason' => 'PARTNER_APP_UNINSTALLED',
        ]);

        // Actualizar todos los números de teléfono asociados
        $phoneNumbers = $businessAccount->phoneNumbers ?? collect();
        $phoneNumbers->each(function($phone) {
            $phone->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
                'disconnection_reason' => 'PARTNER_APP_UNINSTALLED',
            ]);
        });

        Log::channel('whatsapp')->info('✅ [ACCOUNT] Business account marked as disconnected', [
            'business_account_id' => $businessAccount->whatsapp_business_id,
            'waba_id' => $wabaId,
            'phone_numbers_affected' => $phoneNumbers->count()
        ]);

        // Disparar eventos
        $this->firePartnerAppUninstalled($businessAccount, $wabaInfo);
        $this->fireAccountStatusUpdated($businessAccount, 'disconnected');
    }


    /**
     * Maneja cuando un negocio desconecta la cuenta
     */
    protected function handlePartnerRemoved(array $wabaInfo): void
    {
        $wabaId = $wabaInfo['waba_id'] ?? null;
        $ownerBusinessId = $wabaInfo['owner_business_id'] ?? null;

        Log::channel('whatsapp')->warning('🗑️ [ACCOUNT] Partner completely removed', [
            'waba_id' => $wabaId,
            'owner_business_id' => $ownerBusinessId
        ]);

        // Buscar la cuenta de WhatsApp Business afectada
        $businessAccount = WhatsappModelResolver::business_account()
            ->where('whatsapp_business_id', $wabaId)
            ->first();

        if (!$businessAccount) {
            Log::channel('whatsapp')->error('❌ [ACCOUNT] Business account not found for removal', [
                'waba_id' => $wabaId
            ]);
            return;
        }

        // Marcar la cuenta como completamente removida
        $businessAccount->update([
            'status' => 'removed',
            'partner_app_id' => null,
            'disconnected_at' => now(),
            'fully_removed_at' => now(),
            'disconnection_reason' => 'PARTNER_REMOVED',
        ]);

        // Actualizar todos los números de teléfono asociados
        $phoneNumbers = $businessAccount->phoneNumbers ?? collect();
        $phoneNumbers->each(function($phone) {
            $phone->update([
                'status' => 'removed',
                'disconnected_at' => now(),
                'fully_removed_at' => now(),
                'disconnection_reason' => 'PARTNER_REMOVED',
            ]);
        });

        Log::channel('whatsapp')->info('✅ [ACCOUNT] Business account marked as removed', [
            'business_account_id' => $businessAccount->whatsapp_business_id,
            'waba_id' => $wabaId,
            'phone_numbers_affected' => $phoneNumbers->count()
        ]);

        // Disparar eventos
        $this->firePartnerRemoved($businessAccount, $wabaInfo);
        $this->fireAccountStatusUpdated($businessAccount, 'removed');
    }

    /**
     * Procesar mensajes multimedia de ecos SMB (similar a processMediaMessage del padre)
     */
    protected function processSmbMediaMessageEcho(array $echo, Model $contactRecord, Model $whatsappPhone, string $messageType): void
    {
        try {
            $mediaId = $echo[$messageType]['id'] ?? null;
            $caption = $echo[$messageType]['caption'] ?? strtoupper($messageType);
            $mimeType = $echo[$messageType]['mime_type'] ?? null;

            Log::channel('whatsapp')->info('📎 [COEXISTENCE] Procesando archivo multimedia SMB echo', [
                'media_id' => $mediaId,
                'type' => $messageType,
                'mime_type' => $mimeType
            ]);

            if (!$mediaId) {
                Log::channel('whatsapp')->warning('⚠️ [COEXISTENCE] No media ID found in SMB echo', [
                    'message_type' => $messageType
                ]);
                return;
            }

            // Obtener URL del archivo multimedia
            $mediaUrl = $this->getMediaUrl($mediaId, $whatsappPhone);

            if (!$mediaUrl) {
                Log::channel('whatsapp')->error('❌ [COEXISTENCE] Failed to retrieve media URL', [
                    'media_id' => $mediaId
                ]);
                return;
            }

            // Descargar contenido del archivo
            $mediaContent = $this->downloadMedia($mediaUrl, $whatsappPhone);

            if (!$mediaContent) {
                Log::channel('whatsapp')->error('❌ [COEXISTENCE] Failed to download media content', [
                    'media_url' => $mediaUrl
                ]);
                return;
            }

            // Obtener el tipo de media pluralizado según la configuración
            $mediaType = $messageType . 's'; // Por defecto pluralizar el tipo de media

            // Obtener la ruta de almacenamiento configurada desde la config
            $directory = config("whatsapp.media.storage_path.$mediaType");

            if (!$directory) {
                throw new \RuntimeException("No se ha configurado una ruta de almacenamiento para el tipo de media: $mediaType");
            }

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $extension = $this->getFileExtension($mimeType);

            // Si es un archivo de audio y tiene extensión .bin, forzar a .ogg
            if ($messageType === 'audio' && $extension === 'bin') {
                $extension = 'ogg';
            }

            if ($messageType === 'sticker' && $extension === 'bin') {
                $extension = 'webp';
            }

            $fileName = "{$mediaId}.{$extension}";
            if (Str::endsWith($directory, '/')) {
                $directory = rtrim($directory, '/');
            }
            $filePath = "{$directory}/{$fileName}";
            file_put_contents($filePath, $mediaContent);

            // Convertir el path absoluto a relativo para Storage::url
            $relativePath = str_replace(storage_path('app/public/'), '', $directory . '/' . $fileName);

            // Obtener la URL pública
            $publicPath = Storage::url($relativePath);

            Log::channel('whatsapp')->info('💾 [COEXISTENCE] Archivo multimedia guardado', [
                'file_path' => $filePath,
                'public_path' => $publicPath,
                'file_size' => strlen($mediaContent)
            ]);

            // Preparar datos del mensaje
            $messageData = [
                'wa_id' => $echo['id'],
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contactRecord->contact_id,
                'conversation_id' => null,
                'messaging_product' => 'whatsapp',
                'message_method' => 'OUTPUT', // Los ecos son mensajes SALIENTES desde el móvil
                'message_from' => preg_replace('/[\D+]/', '', $echo['from']),
                'message_to' => preg_replace('/[\D+]/', '', $echo['to']),
                'message_type' => strtoupper($messageType),
                'message_content' => $caption,
                'json_content' => json_encode($echo),
                'status' => 'sent', // Asumir enviado para ecos
                'message_context_id' => $this->getContextMessageId($echo),
                'is_smb_echo' => true, // Marcar como eco de SMB
            ];

            // Crear registro del mensaje
            $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
                ['wa_id' => $messageData['wa_id']],
                $messageData
            );

            Log::channel('whatsapp')->info('✅ [COEXISTENCE] Mensaje multimedia SMB echo guardado', [
                'message_id' => $messageRecord->message_id,
                'type' => $messageType
            ]);

            // Crear o actualizar el registro del archivo multimedia en la base de datos
            $mediaFileRecord = WhatsappModelResolver::media_file()->updateOrCreate(
                [
                    'message_id' => $messageRecord->message_id,
                    'media_id' => $mediaId,
                ],
                [
                    'media_type' => $messageType,
                    'file_name' => $fileName,
                    'url' => $publicPath,
                    'mime_type' => $mimeType,
                    'sha256' => $echo[$messageType]['sha256'] ?? null,
                    'file_size' => strlen($mediaContent),
                ]
            );

            Log::channel('whatsapp')->info('✅ [COEXISTENCE] Registro de archivo multimedia creado', [
                'media_file_id' => $mediaFileRecord->media_file_id ?? $mediaFileRecord->id,
                'message_id' => $messageRecord->message_id,
                'url' => $publicPath
            ]);

            // Cargar relación para el evento
            $messageRecord->loadMissing(['mediaFiles']);

            // Disparar evento
            $this->fireSmbMessageEcho($contactRecord, $messageRecord);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('❌ [COEXISTENCE] Error al procesar archivo multimedia SMB echo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_type' => $messageType
            ]);
            // No lanzar excepción para no romper el proceso completo
        }
    }

    /**
     * Procesar mensajes no multimedia de ecos SMB
     */
    protected function processSmbNonMediaMessageEcho(array $echo, Model $contactRecord, Model $whatsappPhone, string $messageType): void
    {
        try {
            // Preparar datos del mensaje
            $messageData = [
                'wa_id' => $echo['id'],
                'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
                'contact_id' => $contactRecord->contact_id,
                'conversation_id' => null,
                'messaging_product' => 'whatsapp',
                'message_method' => 'OUTPUT', // Los ecos son mensajes SALIENTES desde el móvil
                'message_from' => preg_replace('/[\D+]/', '', $echo['from']),
                'message_to' => preg_replace('/[\D+]/', '', $echo['to']),
                'message_type' => strtoupper($messageType),
                'json_content' => json_encode($echo),
                'status' => 'sent', // Asumir enviado para ecos
                'message_context_id' => $this->getContextMessageId($echo),
                'is_smb_echo' => true, // Marcar como eco de SMB
            ];

            // Procesar contenido según el tipo
            switch ($messageType) {
                case 'text':
                    $messageData['message_content'] = $echo['text']['body'] ?? '';
                    break;

                case 'interactive':
                    $messageData['message_content'] = $echo['interactive']['button_reply']['title']
                        ?? $echo['interactive']['list_reply']['title']
                        ?? 'Interactive message';
                    break;

                case 'location':
                    $messageData['message_content'] = "Location: " . ($echo['location']['name'] ?? '');
                    break;

                case 'contacts':
                    $messageData['message_content'] = "Shared contacts: " . count($echo['contacts'] ?? []);
                    break;

                case 'reaction':
                    $messageData['message_content'] = $echo['reaction']['emoji'] ?? null;
                    break;

                case 'unsupported':
                    $messageData = $this->processUnsupportedSmbEcho($echo, $messageData);
                    break;

                default:
                    $messageData['message_content'] = $this->getMessageContentForType($messageType, $echo)
                        ?? 'SMB echo message';
            }

            Log::channel('whatsapp')->info('💾 [COEXISTENCE] Guardando mensaje SMB echo no multimedia', [
                'wa_id' => $messageData['wa_id'],
                'type' => $messageData['message_type'],
                'content_preview' => substr($messageData['message_content'], 0, 50)
            ]);

            $messageRecord = WhatsappModelResolver::message()->firstOrCreate(
                ['wa_id' => $messageData['wa_id']],
                $messageData
            );

            Log::channel('whatsapp')->info('✅ [COEXISTENCE] SMB message echo guardado exitosamente', [
                'message_id' => $messageRecord->message_id,
                'wa_id' => $messageRecord->wa_id,
                'type' => $messageType,
                'is_smb_echo' => $messageRecord->is_smb_echo
            ]);

            // Disparar evento
            if ($messageType === 'unsupported') {
                $this->fireUnsupportedSmbMessageEcho($contactRecord, $messageRecord, $echo);
            } else {
                $this->fireSmbMessageEcho($contactRecord, $messageRecord);
            }

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('❌ [COEXISTENCE] Error al guardar mensaje SMB echo no multimedia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_data' => $messageData ?? []
            ]);
        }
    }

    /**
     * Procesar mensajes no soportados en ecos SMB
     */
    protected function processUnsupportedSmbEcho(array $echo, array $messageData): array
    {
        $errorCode = $echo['errors'][0]['code'] ?? null;
        $errorTitle = $echo['errors'][0]['title'] ?? 'Unsupported content';
        $errorMessage = $echo['errors'][0]['message'] ?? 'Unknown error';
        $errorDetails = $echo['errors'][0]['error_data']['details'] ?? 'No additional details available';

        $content = "Unsupported message. Error: $errorCode - $errorTitle: $errorDetails";

        // Actualizar los datos del mensaje con información del error
        $messageData['message_content'] = $content;
        $messageData['code_error'] = $errorCode;
        $messageData['title_error'] = $errorTitle;
        $messageData['message_error'] = $errorMessage;
        $messageData['details_error'] = $errorDetails;

        Log::channel('whatsapp')->warning('⚠️ [COEXISTENCE] Mensaje no soportado en eco SMB', [
            'wa_id' => $echo['id'],
            'error_code' => $errorCode,
            'error_title' => $errorTitle,
            'error_details' => $errorDetails
        ]);

        return $messageData;
    }

    // =========================================================================
    // CUSTOM HOOKS - Personaliza estos métodos según tus necesidades
    // =========================================================================

    /**
     * Hook: Después de sincronizar el historial completo
     */
    protected function afterHistorySync(array $data): void
    {
        // Ejemplo: Notificar al administrador que el historial ha sido sincronizado
        // Ejemplo: Actualizar estadísticas de mensajes históricos
        // Ejemplo: Indexar mensajes históricos para búsqueda

        $phoneNumberId = $data['metadata']['phone_number_id'] ?? null;

        if ($phoneNumberId) {
            // Puedes disparar un evento personalizado
            // event(new HistorySyncCompletedEvent($phoneNumberId));

            Log::channel('whatsapp')->info('📊 [COEXISTENCE] Post-procesamiento de historial completado', [
                'phone_number_id' => $phoneNumberId
            ]);
        }
    }

    /**
     * Hook: Después de sincronizar contactos
     */
    protected function afterContactsSync(array $data): void
    {
        // Ejemplo: Sincronizar con CRM externo
        // Ejemplo: Actualizar segmentación de contactos
        // Ejemplo: Enviar notificaciones de nuevos contactos

        $contactsCount = count($data['state_sync'] ?? []);

        Log::channel('whatsapp')->info('📊 [COEXISTENCE] Post-procesamiento de contactos completado', [
            'contacts_synced' => $contactsCount
        ]);
    }

    /**
     * Hook: Después de procesar ecos de mensajes
     */
    protected function afterSmbMessageEchoes(array $data): void
    {
        // Ejemplo: Actualizar UI en tiempo real vía WebSockets
        // Ejemplo: Enviar notificaciones push
        // Ejemplo: Actualizar métricas de mensajes enviados

        $messagesCount = count($data['message_echoes'] ?? []);

        // Ejemplo de broadcast en tiempo real
        foreach ($data['message_echoes'] ?? [] as $echo) {
            // broadcast(new MessageSentFromMobileEvent($echo));
        }

        Log::channel('whatsapp')->info('📊 [COEXISTENCE] Post-procesamiento de ecos completado', [
            'messages_echoed' => $messagesCount
        ]);
    }

    /**
     * Hook: Antes de procesar mensaje histórico individual
     */
    protected function beforeProcessHistoryMessage(array $message, Model $contact, Model $whatsappPhone, int $phase): void
    {
        // Lógica personalizada antes de guardar mensaje histórico
        // Ejemplo: Validar duplicados, filtrar por tipo, etc.
    }

    /**
     * Hook: Después de procesar mensaje histórico individual
     */
    protected function afterProcessHistoryMessage(array $message, Model $contact, Model $whatsappPhone, int $phase): void
    {
        // Lógica personalizada después de guardar mensaje histórico
        // Ejemplo: Indexar mensaje, actualizar estadísticas, etc.
    }

    /**
     * Hook: Validar si se debe sincronizar un contacto
     */
    protected function shouldSyncContact(array $syncItem): bool
    {
        // Lógica de validación personalizada
        // Ejemplo: Ignorar contactos de prueba, números bloqueados, etc.

        $phoneNumber = $syncItem['contact']['phone_number'] ?? '';

        // Ejemplo: No sincronizar números de prueba
        if (str_starts_with($phoneNumber, '1234567890')) {
            return false;
        }

        return true;
    }

    /**
     * Hook: Después de sincronizar un contacto individual
     */
    protected function afterProcessContactSync(array $syncItem, Model $whatsappPhone): void
    {
        // Lógica personalizada después de sincronizar contacto
        // Ejemplo: Actualizar tags, categorías, asignar agentes, etc.

        $action = $syncItem['action'] ?? '';
        $contactData = $syncItem['contact'] ?? [];

        if ($action === 'create') {
            // Nuevo contacto creado desde la app móvil
            Log::channel('whatsapp')->debug('📱 Nuevo contacto desde móvil', $contactData);
        }
    }

    /**
     * Hook: Antes de procesar eco de mensaje
     */
    protected function beforeProcessSmbMessageEcho(array $echo, Model $whatsappPhone): void
    {
        // Lógica personalizada antes de guardar eco de mensaje
        // Ejemplo: Validar que el mensaje no sea duplicado, filtrar spam, etc.
    }

    /**
     * Hook: Después de procesar eco de mensaje
     */
    protected function afterProcessSmbMessageEcho(array $echo, Model $whatsappPhone): void
    {
        // Lógica personalizada después de guardar eco de mensaje
        // Ejemplo: Actualizar interfaz en tiempo real, notificaciones, etc.

        $messageType = $echo['type'] ?? '';
        $to = $echo['to'] ?? '';

        Log::channel('whatsapp')->debug('📤 Mensaje enviado desde móvil', [
            'type' => $messageType,
            'to' => $to
        ]);

        // Ejemplo: Broadcast en tiempo real para actualizar UI
        // broadcast(new SmbMessageSentEvent([
        //     'message' => $echo,
        //     'phone_number_id' => $whatsappPhone->phone_number_id
        // ]));
    }

    /**
     * Dispara evento para mensajes eco de SMB
     */
    protected function fireSmbMessageEcho(Model $contactRecord, Model $messageRecord): void
    {
        // Disparar el evento de mensaje recibido estándar
        $this->fireMessageReceived($contactRecord, $messageRecord);

        Log::channel('whatsapp')->debug('🎉 [COEXISTENCE] Evento SMB message echo disparado', [
            'message_id' => $messageRecord->message_id,
            'contact_id' => $contactRecord->contact_id
        ]);
    }

    /**
     * Dispara evento para sincronización de historial
     */
    protected function fireCoexistenceHistorySynced(array $data): void
    {
        $event = config('whatsapp.events.coexistence.history_synced');
        event(new $event($data));
    }

    /**
     * Disparar evento para mensajes no soportados en ecos SMB
     */
    protected function fireUnsupportedSmbMessageEcho(Model $contactRecord, Model $messageRecord, array $echo): void
    {
        $errorCode = $echo['errors'][0]['code'] ?? null;
        $errorTitle = $echo['errors'][0]['title'] ?? 'Unsupported content';
        $errorMessage = $echo['errors'][0]['message'] ?? 'Unknown error';
        $errorDetails = $echo['errors'][0]['error_data']['details'] ?? 'No additional details available';

        // Primero disparar el evento estándar de SMB echo
        $this->fireSmbMessageEcho($contactRecord, $messageRecord);

        // Luego disparar evento específico para mensajes no soportados
        $event = config('whatsapp.events.messages.unsupported.received');
        if ($event) {
            event(new $event([
                'contact' => $contactRecord,
                'message' => $messageRecord,
                'title_error' => $errorTitle,
                'message_error' => $errorMessage,
                'details_error' => $errorDetails,
                'is_smb_echo' => true, // Indicar que es un eco SMB
            ]));
        }

        Log::channel('whatsapp')->debug('🎉 [COEXISTENCE] Evento unsupported SMB message echo disparado', [
            'message_id' => $messageRecord->message_id,
            'contact_id' => $contactRecord->contact_id,
            'error_code' => $errorCode
        ]);
    }

    /**
     * Dispara evento para sincronización de contactos
     */
    protected function fireCoexistenceContactSynced(array $data): void
    {
        $event = config('whatsapp.events.coexistence.contact_synced');
        event(new $event($data));
    }

    /**
     * Dispara evento para ecos de mensajes SMB
     */
    protected function fireCoexistenceSmbMessageEcho(array $data): void
    {
        $event = config('whatsapp.events.coexistence.smb_message_echo');
        event(new $event($data));
    }

    /**
     * Dispara evento para actualizaciones de cuenta
     */
    protected function fireCoexistenceAccountUpdated(array $data): void
    {
        $event = config('whatsapp.events.coexistence.account_updated');
        event(new $event($data));
    }

    /**
     * Dispara evento para actualizaciones de cuenta genéricas
     */
    protected function fireAccountUpdate(array $data): void
    {
        $event = config('whatsapp.events.account.update');
        if ($event) {
            event(new $event($data));

            Log::channel('whatsapp')->debug('🎉 [ACCOUNT] Account update event fired', [
                'event' => $data['event'] ?? 'unknown'
            ]);
        }
    }

    /**
     * Dispara evento para desinstalación de app partner
     */
    protected function firePartnerAppUninstalled(Model $businessAccount, array $wabaInfo): void
    {
        $event = config('whatsapp.events.partner.app_uninstalled');
        if( $event ) {
            $eventData = [
                'business_account' => $businessAccount,
                'waba_info' => $wabaInfo,
                'timestamp' => now(),
                'event_type' => 'PARTNER_APP_UNINSTALLED'
            ];

            event(new $event($eventData));

            Log::channel('whatsapp')->info('🎉 [ACCOUNT] Partner app uninstalled event fired', [
                'business_account_id' => $businessAccount->whatsapp_business_id,
                'waba_id' => $wabaInfo['waba_id'] ?? null
            ]);
        }
    }

    /**
     * Dispara evento para remoción completa del partner
     */
    protected function firePartnerRemoved(Model $businessAccount, array $wabaInfo): void
    {
        $event = config('whatsapp.events.partner.partner_removed');
        if( $event ) {
            $eventData = [
                'business_account' => $businessAccount,
                'waba_info' => $wabaInfo,
                'timestamp' => now(),
                'event_type' => 'PARTNER_REMOVED'
            ];

            event(new $event($eventData));

            Log::channel('whatsapp')->info('🎉 [ACCOUNT] Partner removed event fired', [
                'business_account_id' => $businessAccount->whatsapp_business_id,
                'waba_id' => $wabaInfo['waba_id'] ?? null
            ]);
        }
    }

    /**
     * Dispara evento para actualización de estado de cuenta
     */
    protected function fireAccountStatusUpdated(Model $businessAccount, string $status): void
    {
        $event = config('whatsapp.events.account.status_updated');
        if( $event ) {
            $eventData = [
                'business_account' => $businessAccount,
                'new_status' => $status,
                'timestamp' => now(),
                'event_type' => 'ACCOUNT_STATUS_UPDATED'
            ];

            event(new $event($eventData));

            Log::channel('whatsapp')->debug('🔔 [ACCOUNT] Account status updated event fired', [
                'business_account_id' => $businessAccount->whatsapp_business_id,
                'status' => $status
            ]);
        }
    }

    /**
     * Dispara evento para instalación de app partner
     */
    protected function firePartnerAppInstalled(Model $businessAccount, array $wabaInfo): void
    {
        $event = config('whatsapp.events.partner.app_installed');
        if( $event ) {
            $eventData = [
                'business_account' => $businessAccount,
                'waba_info' => $wabaInfo,
                'timestamp' => now(),
                'event_type' => 'PARTNER_APP_INSTALLED'
            ];

            event(new $event($eventData));

            Log::channel('whatsapp')->info('🎉 [ACCOUNT] Partner app installed event fired', [
                'business_account_id' => $businessAccount->whatsapp_business_id,
                'waba_id' => $wabaInfo['waba_id'] ?? null
            ]);
        }
    }

    /**
     * Dispara evento para adición de partner
     */
    protected function firePartnerAdded(Model $businessAccount, array $wabaInfo): void
    {
        $event = config('whatsapp.events.partner.partner_added');
        if( $event ) {
            $eventData = [
                'business_account' => $businessAccount,
                'waba_info' => $wabaInfo,
                'timestamp' => now(),
                'event_type' => 'PARTNER_ADDED'
            ];

            event(new $event($eventData));

            Log::channel('whatsapp')->info('🎉 [ACCOUNT] Partner added event fired', [
                'business_account_id' => $businessAccount->whatsapp_business_id,
                'waba_id' => $wabaInfo['waba_id'] ?? null
            ]);
        }
    }

    /**
     * Maneja las actualizaciones de capacidades de negocio (límites de mensajes, etc.)
     * 
     * @param array $value Datos del webhook business_capability_update
     * @param array $payload Payload completo del webhook
     */
    protected function handleBusinessCapabilityUpdate(array $value, array $payload): void
    {
        Log::channel('whatsapp')->info('🔄 [BUSINESS_CAPABILITY] Processing business capability update', $value);

        // Obtener el ID de la cuenta empresarial desde el payload
        $wabaId = data_get($payload, 'entry.0.id');
        
        if (!$wabaId) {
            Log::channel('whatsapp')->warning('⚠️ [BUSINESS_CAPABILITY] No WABA ID found in payload', $payload);
            return;
        }

        // Buscar la cuenta empresarial
        $businessAccount = WhatsappModelResolver::business_account()->find($wabaId);
        
        if (!$businessAccount) {
            Log::channel('whatsapp')->warning('⚠️ [BUSINESS_CAPABILITY] Business account not found', [
                'waba_id' => $wabaId
            ]);
            return;
        }

        // Obtener el límite de mensajes (puede venir en diferentes formatos según la versión)
        $messagingLimitTier = null;
        $messagingLimitValue = null;

        // Versión 24.0 y posteriores: max_daily_conversations_per_business viene como string (TIER_2K, etc.)
        if (isset($value['max_daily_conversations_per_business'])) {
            $messagingLimitTier = $value['max_daily_conversations_per_business'];
            $messagingLimitValue = MessagingLimitHelper::convertTierToLimitValue($messagingLimitTier);
        }
        // Versión 23.0 y anteriores: max_daily_conversation_per_phone viene como número
        elseif (isset($value['max_daily_conversation_per_phone'])) {
            $limitValue = $value['max_daily_conversation_per_phone'];
            
            // Convertir el número a tier (el helper maneja -1 como ilimitado)
            $messagingLimitTier = MessagingLimitHelper::convertLimitValueToTier($limitValue);
            $messagingLimitValue = ($limitValue == -1) ? null : $limitValue;
        }

        // Actualizar la cuenta empresarial
        if ($messagingLimitTier !== null) {
            $businessAccount->messaging_limit_tier = $messagingLimitTier;
            $businessAccount->messaging_limit_value = $messagingLimitValue;
            $businessAccount->save();

            Log::channel('whatsapp')->info('✅ [BUSINESS_CAPABILITY] Messaging limit updated', [
                'waba_id' => $wabaId,
                'messaging_limit_tier' => $messagingLimitTier,
                'messaging_limit_value' => $messagingLimitValue,
            ]);
        } else {
            Log::channel('whatsapp')->warning('⚠️ [BUSINESS_CAPABILITY] No messaging limit found in webhook', $value);
        }
    }

}