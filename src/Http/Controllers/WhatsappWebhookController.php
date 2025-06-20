<?php

namespace ScriptDevelop\WhatsappManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
USE ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;
//use ScriptDevelop\WhatsappManager\Models\Contact;
//use ScriptDevelop\WhatsappManager\Models\Conversation;
//use ScriptDevelop\WhatsappManager\Models\Message;
//use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\Helpers\CountryCodes;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request): Response|JsonResponse
    {
        $verifyToken = config('whatsapp-webhook.verify_token', env('WHATSAPP_VERIFY_TOKEN'));

        if ($request->isMethod('get') && $request->has(['hub_mode', 'hub_challenge', 'hub_verify_token'])) {
            return $this->verifyWebhook($request, $verifyToken);
        }

        if ($request->isMethod('post')) {
            return $this->processIncomingMessage($request);
        }
        Log::channel('whatsapp')->error('Registro webhook invalido', [$request]);
        return response()->json(['error' => 'Invalid request method.'], 400);
    }

    protected function verifyWebhook(Request $request, string $verifyToken): Response|JsonResponse
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

    protected function processIncomingMessage(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::channel('whatsapp')->info('Received WhatsApp Webhook Payload:', $payload);

        $value = data_get($payload, 'entry.0.changes.0.value');

        if (!$value) {
            Log::channel('whatsapp')->warning('No value found in webhook payload.', $payload);
            return response()->json(['error' => 'Invalid payload.'], 422);
        }

        if (isset($value['messages'][0])) {
            $this->handleIncomingMessage(
                $value['messages'][0] ?? [],
                $value['contacts'][0] ?? null,
                $value['metadata'] ?? null
            );
        }

        if (isset($value['statuses'][0])) {
            $this->handleStatusUpdate($value['statuses'][0] ?? []);
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

        $contactRecord = WhatsappModelResolver::contact()->firstOrCreate(
            [
                'country_code' => $countryCode,
                'phone_number' => $phoneNumber,
            ],
            [
                'wa_id' => $contact['wa_id'],
                'contact_name' => $contact['profile']['name'] ?? null,
            ]
        );

        $contactRecord->update([
            'wa_id' => $contact['wa_id'], // Siempre actualizarlo
            'contact_name' => $contact['profile']['name'] ?? $contactRecord->contact_name,
        ]);

        $apiPhoneNumberId = $metadata['phone_number_id'] ?? null;

        $whatsappPhone = null;
        if ($apiPhoneNumberId) {
            $whatsappPhone = WhatsappModelResolver::phone_number()->where('api_phone_number_id', $apiPhoneNumberId)->first();
        }

        if (!$whatsappPhone) {
            Log::channel('whatsapp')->error('No matching WhatsappPhoneNumber found for api_phone_number_id.', [
                'api_phone_number_id' => $apiPhoneNumberId,
            ]);
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
                'status' => 'received'
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
                'status' => 'received'
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

        $directory = storage_path("app/public/whatsapp/{$message['type']}s/");
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
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
        $filePath = "{$directory}{$fileName}";
        file_put_contents($filePath, $mediaContent);

        $publicPath = Storage::url("public/whatsapp/{$message['type']}s/{$fileName}");

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
                'json_content' => json_encode($message),
                'status' => 'received'
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
                'status' => 'received'
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
                'status' => 'received'
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
                'status' => 'received'
            ]);

        Log::channel('whatsapp')->info('Reacción procesada.', [
            'message_id' => $messageRecord->message_id,
            'reaction' => $reaction
        ]);

        return $messageRecord;
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

            case 'failed': $this->fireMessageFailed($messageUpdated);
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

    private function compareValues($value1, string $operator, $value2): bool
    {
        try {
            switch ($operator) {
                case '==': return $value1 == $value2;
                case '!=': return $value1 != $value2;
                case '>': return $value1 > $value2;
                case '<': return $value1 < $value2;
                case '>=': return $value1 >= $value2;
                case '<=': return $value1 <= $value2;
                case 'contains': return stripos($value1, $value2) !== false;
                default: return false;
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error("Error comparando valores: {$e->getMessage()}", [
                'value1' => $value1,
                'operator' => $operator,
                'value2' => $value2
            ]);
            return false;
        }
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

    private function handleUnrecognizedMessage(Model $whatsappPhone, Model $contact): void
    {
        $this->sendDefaultFallbackMessage($whatsappPhone, $contact);
    }

    private function sendDefaultFallbackMessage($whatsappPhone, $contact): void
    {
        try {
            $service = app(MessageDispatcherService::class);
            [$countryCode, $number] = $this->splitPhoneNumber($contact->wa_id);

            $service->sendTextMessage(
                $whatsappPhone->phone_number_id,
                $countryCode,
                $number,
                "Lo siento, no entendí tu mensaje. Por favor intenta con otra opción.",
                false
            );
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Fallback message failed: '.$e->getMessage());
        }
    }

    private function sendErrorFallbackMessage($whatsappPhone, $contact): void
    {
        $this->sendDefaultFallbackMessage($whatsappPhone, $contact);
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
}
