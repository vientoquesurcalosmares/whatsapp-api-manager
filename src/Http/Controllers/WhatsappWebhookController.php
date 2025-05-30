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
use ScriptDevelop\WhatsappManager\Services\SessionManager;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Conversation;
use ScriptDevelop\WhatsappManager\Models\ChatSession;
use ScriptDevelop\WhatsappManager\Models\Flow;
use ScriptDevelop\WhatsappManager\Models\FlowStep;
use ScriptDevelop\WhatsappManager\Models\Message;
use ScriptDevelop\WhatsappManager\Models\UserResponse;
use ScriptDevelop\WhatsappManager\Models\WhatsappBot;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\Helpers\CountryCodes;
use ScriptDevelop\WhatsappManager\Models\MediaFile;
use ScriptDevelop\WhatsappManager\Enums\StepType;

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
            'message' => $message,
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

        $contactRecord = Contact::firstOrCreate(
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
            $whatsappPhone = WhatsappPhoneNumber::where('api_phone_number_id', $apiPhoneNumberId)->first();
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
            $textContent = $messageRecord->message_content ?? null;
        }

        // Manejar mensajes interactivos (botones, listas)
        if ($messageType === 'interactive') {
            $messageRecord = $this->processInteractiveMessage($message, $contactRecord, $whatsappPhone);
            $textContent = $messageRecord->message_content ?? null;
        }

        // Manejar mensajes de media
        if (in_array($messageType, ['image', 'audio', 'video', 'document'])) {
            $this->processMediaMessage($message, $contactRecord, $whatsappPhone);
        }



        Log::channel('whatsapp')->info('Incoming message processed.', [
            'message_id' => $message['id'],
            'contact_id' => $contactRecord->contact_id,
            'phone_number' => $fullPhone,
            'message_type' => $messageType,
            'Message' => $message['text']['body'],
            'Text Content' => $textContent,
        ]);

        // Si tenemos contenido de texto, procesar el flujo
        if ($textContent && $messageRecord) {
            try {
                $bot = $whatsappPhone->bots()->where('is_enable', true)->first();
                
                if (!$bot) {
                    Log::channel('whatsapp')->warning('Bot inactivo o no encontrado');
                    return;
                }

                $sessionManager = app(SessionManager::class);
                $session = $sessionManager->getOrCreateSession($contactRecord, $bot);
                
                $flowId = $this->determineFlow($bot, $textContent, $contactRecord);

                $messageId = $messageRecord->message_id;
                
                if ($flowId) {
                    // Si es una nueva sesión o el flujo ha cambiado
                    if (!$session->flow_id || $session->flow_id !== $flowId) {
                        $session->update([
                            'flow_id' => $flowId,
                            'current_step_id' => null,
                            'flow_status' => 'in_progress'
                        ]);
                    }
                    
                    $this->processFlowStep($session, $textContent, $messageRecord->message_id);
                } else {
                    Log::channel('whatsapp')->warning('Flujo no determinado', ['text' => $textContent]);
                    $this->handleUnrecognizedMessage($whatsappPhone, $contactRecord);
                }
                
            } catch (\Exception $e) {
                Log::channel('whatsapp')->error('Error en flujo: '.$e->getMessage());
                $this->sendErrorFallbackMessage($whatsappPhone, $contactRecord);
            }
        }
    }

    protected function processTextMessage(array $message, Contact $contact, WhatsappPhoneNumber $whatsappPhone): ?Message
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

        $messageRecord = Message::create([
            'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
            'contact_id' => $contact->contact_id,
            'wa_id' => $message['id'],
            'conversation_id' => null, // Esto se puede actualizar más tarde si es necesario
            'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
            'message_from' => $message['from'],
            'message_to' => $whatsappPhone->display_phone_number,
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

    protected function processInteractiveMessage(array $message, Contact $contact, WhatsappPhoneNumber $whatsappPhone): ?Message
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
        $messageRecord = Message::create([
            'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
            'contact_id' => $contact->contact_id,
            'wa_id' => $message['id'],
            'conversation_id' => null,
            'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
            'message_from' => $message['from'],
            'message_to' => $whatsappPhone->display_phone_number,
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

    protected function processMediaMessage(array $message, Contact $contact, WhatsappPhoneNumber $whatsappPhone): void
    {
        $mediaId = $message[$message['type']]['id'] ?? null;
        $caption = $message[$message['type']]['caption'] ?? strtoupper($message['type']);
        $mimeType = $message[$message['type']]['mime_type'] ?? null;

        if (!$mediaId) {
            Log::channel('whatsapp')->warning('No media ID found in message.', $message);
            return;
        }

        $mediaUrl = $this->getMediaUrl($mediaId, $whatsappPhone);

        if (!$mediaUrl) {
            Log::channel('whatsapp')->error('Failed to retrieve media URL.', ['media_id' => $mediaId]);
            return;
        }

        $mediaContent = $this->downloadMedia($mediaUrl, $whatsappPhone);

        if (!$mediaContent) {
            Log::channel('whatsapp')->error('Failed to download media content.', ['media_url' => $mediaUrl]);
            return;
        }

        $directory = storage_path("app/public/whatsapp/{$message['type']}s/");
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $fileName = "{$mediaId}.{$this->getFileExtension($mimeType)}";
        $filePath = "{$directory}{$fileName}";
        file_put_contents($filePath, $mediaContent);

        $publicPath = Storage::url("public/whatsapp/{$message['type']}s/{$fileName}");

        // Crear el registro del mensaje en la base de datos
        $messageRecord = Message::create([
            'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
            'contact_id' => $contact->contact_id,
            'wa_id' => $message['id'],
            'conversation_id' => null, // Esto se puede actualizar más tarde si es necesario
            'messaging_product' => $message['messaging_product'] ?? 'whatsapp',
            'message_from' => $message['from'],
            'message_to' => $whatsappPhone->display_phone_number,
            'message_type' => strtoupper($message['type']),
            'message_content' => $caption,
            'json_content' => json_encode($message),
            'status' => 'received'
        ]);

        // Crear el registro del archivo multimedia en la base de datos
        MediaFile::create([
            'message_id' => $messageRecord->message_id,
            'media_type' => $message['type'],
            'file_name' => $fileName,
            'url' => $publicPath,
            'media_id' => $mediaId,
            'mime_type' => $mimeType,
            'sha256' => $message[$message['type']]['sha256'] ?? null,
        ]);

        Log::channel('whatsapp')->info('Media file and message saved.', [
            'message_id' => $messageRecord->message_id,
            'file_path' => $filePath,
            'public_url' => $publicPath,
        ]);
    }

    private function getMediaUrl(string $mediaId, WhatsappPhoneNumber $whatsappPhone): ?string
    {
        $url = env('WHATSAPP_API_URL') . '/' . env('WHATSAPP_API_VERSION') . "/$mediaId?phone_number_id=" . $whatsappPhone->api_phone_number_id;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $whatsappPhone->businessAccount->api_token,
        ])->get($url);

        return $response->json()['url'] ?? null;
    }

    private function downloadMedia(string $url, WhatsappPhoneNumber $whatsappPhone): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $whatsappPhone->businessAccount->api_token,
        ])->get($url);

        return $response->successful() ? $response->body() : null;
    }

    private function getFileExtension(?string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'audio/ogg' => 'ogg',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf',
            default => 'bin',
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

        $messageRecord = Message::where('wa_id', $messageId)->first();

        if (!$messageRecord) {
            Log::channel('whatsapp')->warning('Message record not found for status update.', ['wa_id' => $messageId]);
            return;
        }

        // 1. Actualizar estado del mensaje
        $this->updateMessageStatus($messageRecord, $statusValue, $timestamp);

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
        $codes = CountryCodes::list();

        usort($codes, static fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($fullPhone, $code)) {
                $phoneNumber = substr($fullPhone, strlen($code));
                return [$code, $phoneNumber];
            }
        }

        return [null, null];
    }

    private function updateMessageStatus(Message $message, string $status, ?string $timestamp): void
    {
        $updateData = ['status' => $status];

        if ($timestamp) {
            $date = \Carbon\Carbon::createFromTimestamp($timestamp);

            match($status) {
                'sent' => $updateData['sent_at'] = $date,
                'delivered' => $updateData['delivered_at'] = $date,
                'read' => $updateData['read_at'] = $date,
                default => null
            };
        }

        $message->update($updateData);
    }

    private function processConversationData(Message $message, array $status): void
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

        $conversation = Conversation::updateOrCreate(
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

    protected function determineFlow(WhatsappBot $bot, string $text, Contact $contact): ?string
    {
        // 1. Buscar flujo por palabra clave - CON EAGER LOADING
        $flows = $bot->flows()
            ->with(['triggers.triggerable']) // ¡Relaciones críticas!
            ->get();

        foreach ($flows as $flow) {
            if ($flow->matchesTrigger($text)) {
                return $flow->flow_id;
            }
        }
        
        // 2. Si hay sesión previa, continuar con el mismo flujo
        $lastSession = ChatSession::where('contact_id', $contact->contact_id)
            ->where('assigned_bot_id', $bot->whatsapp_bot_id)
            ->latest()
            ->first();
            
        if ($lastSession && $lastSession->flow_status !== 'completed') {
            return $lastSession->flow_id;
        }
        
        // 3. Usar flujo por defecto
        return $bot->default_flow_id;
    }

    protected function processFlowStep(
        ChatSession $session, 
        string $userInput,
        string $messageId
    ): void
    {
        // 1. Obtener paso actual (asegurarse que está cargado)
        if (!$session->relationLoaded('currentStep')) {
            $session->load('currentStep');
        }
        
        $currentStep = $session->currentStep;

        // 2. Si no hay paso actual, usar entryPoint del flujo
        if (!$currentStep) {
            if (!$session->relationLoaded('flow.entryPoint')) {
                $session->load('flow.entryPoint');
            }
            
            $currentStep = $session->flow->entryPoint;
            
            if (!$currentStep) {
                Log::channel('whatsapp')->error('No hay paso inicial definido para el flujo', [
                    'flow_id' => $session->flow_id
                ]);
                $this->handleUnrecognizedMessage(
                    $session->whatsappPhone ?? $session->bot->phoneNumber,
                    $session->contact
                );
                return;
            }
            
            $session->update(['current_step_id' => $currentStep->step_id]);
            $session->currentStep = $currentStep; // Actualizar en memoria
        }

        // 3. Cargar relaciones necesarias si no están cargadas
        $loadRelations = [];
        if (!$session->relationLoaded('bot')) $loadRelations[] = 'bot';
        if (!$session->relationLoaded('contact')) $loadRelations[] = 'contact';
        if (!$session->relationLoaded('whatsappPhone')) $loadRelations[] = 'whatsappPhone';
        
        if (!empty($loadRelations)) {
            $session->load($loadRelations);
        }

        // 4. Verificar phoneNumber
        $phoneNumberModel = $session->whatsappPhone ?? $session->bot->phoneNumber;
        
        if (!$phoneNumberModel) {
            Log::error('No se pudo obtener phoneNumber', [
                'session_id' => $session->session_id,
                'bot_id' => $session->assigned_bot_id
            ]);
            return;
        }

        // 5. Guardar respuesta y determinar siguiente paso
        $this->saveUserResponse($session, $currentStep, $userInput, $messageId);
        $nextStep = $this->determineNextStep($currentStep, $userInput, $session);

        // 6. Actualizar sesión
        $updateData = ['flow_status' => $nextStep ? 'in_progress' : 'completed'];
        
        if ($nextStep) {
            $updateData['current_step_id'] = $nextStep->step_id;
        }
        
        $session->update($updateData);

        // 7. Enviar respuesta del paso correspondiente
        $stepToSend = $nextStep ?: $currentStep;
        $this->sendStepResponse($stepToSend, $session->contact, $phoneNumberModel);
    }

    private function saveUserResponse(
        ChatSession $session, 
        FlowStep $step, 
        string $response,
        string $messageId
    ): void {
        UserResponse::create([
            'session_id' => $session->session_id,
            'flow_step_id' => $step->step_id,
            'message_id' => $messageId, // ID del mensaje recibido
            'field_name' => $step->variable_name, // Asumiendo que el paso tiene un nombre de variable
            'field_value' => $response, // Nombre de campo corregido
            'contact_id' => $session->contact_id, // Contacto obtenido de la sesión
        ]);
    }

    private function determineNextStep(FlowStep $currentStep, string $userInput, ChatSession $session): ?FlowStep
    {
        // 1. Si es paso terminal, no hay siguiente paso
        if ($currentStep->is_terminal) {
            return null;
        }

        // 2. Buscar transiciones condicionales
        foreach ($currentStep->transitions as $transition) {
            if ($this->evaluateCondition($transition, $userInput, $session)) {
                return $transition->toStep;
            }
        }

        // 3. Transición directa por orden
        return $currentStep->flow->steps()
            ->where('order', '>', $currentStep->order)
            ->orderBy('order')
            ->first();
    }

    private function evaluateCondition($transition, string $userInput, ChatSession $session): bool
    {
        $config = $transition->condition_config;
        
        if (!$config || empty($config)) {
            return true; // Transición incondicional
        }
        
        switch ($config['type'] ?? null) {
            case 'variable_value':
                $variable = $this->getVariableValue($config['variable'], $session);
                return $this->compareValues($variable, $config['operator'], $config['value']);
                
            case 'text_match':
                return $userInput === $config['value'];
                
            case 'regex':
                return preg_match($config['pattern'], $userInput) === 1;
                
            default:
                return false;
        }
    }

    private function sendStepResponse(FlowStep $step, Contact $contact, WhatsappPhoneNumber $phoneNumber): void
    {
        $service = app(MessageDispatcherService::class);
        
        foreach ($step->messages as $message) {
            switch ($message->message_type) {
                case 'text':
                    $this->sendTextMessage($message, $contact, $phoneNumber);
                    break;
                    
                case 'interactive_buttons':
                case 'interactive_list':
                    $this->sendInteractiveResponse($message, $contact, $phoneNumber);
                    break;
                    
                default:
                    $content = $this->getMessageContent($message);
                    $service->sendTextMessage(
                        $phoneNumber->phone_number_id,
                        $contact->country_code,
                        $contact->phone_number,
                        $content,
                        false
                    );
            }
            
            // Respeta el retraso entre mensajes
            if ($message->delay_seconds > 0) {
                sleep($message->delay_seconds);
            }
        }
    }

    private function getVariableValue(string $variableName, ChatSession $session)
    {
        // Buscar la variable en las respuestas guardadas
        $response = UserResponse::where('session_id', $session->session_id)
            ->whereHas('step', function($query) use ($variableName) {
                $query->where('variable_name', $variableName);
            })
            ->latest()
            ->first();
            
        return $response ? $response->response_value : null;
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

    private function sendInteractiveResponse($message, Contact $contact, WhatsappPhoneNumber $phoneNumber): void
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

    private function handleUnrecognizedMessage(WhatsappPhoneNumber $whatsappPhone, Contact $contact): void
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
}
