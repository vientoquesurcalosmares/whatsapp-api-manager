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
            $textContent = $this->processTextMessage($message, $contactRecord, $whatsappPhone);
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

        // Solo procesar flujos si es un mensaje de texto válido
        if ($textContent) {
            // 1. Obtener bot asociado
            $bot = $whatsappPhone->bots()->firstWhere('is_enable', true);

            if (!$bot) {
                Log::channel('whatsapp')->error('Bot no encontrado para el número', [
                    'phone' => $whatsappPhone->display_phone_number
                ]);
                return; // Detener ejecución
            }

            // 2. Determinar flujo a ejecutar
            $flowId = $this->determineFlow($bot, $textContent);

            if (!$flowId) {
                Log::channel('whatsapp')->warning('Flujo no determinado', [
                    'text' => $textContent
                ]);
                return;
            }

            // 3. Gestionar sesión
            $session = app(SessionManager::class)->getOrCreateSession(
                $contactRecord,
                $bot,
                $flowId
            );

            // 4. Procesar paso actual
            $this->processFlowStep($session, $textContent);

            Log::channel('whatsapp')->info('Flow step processed.', [
                'bot_id' => $bot->bot_name,
                'session_id' => $session->session_id,
                'current_step' => $session->currentStep->step_id,
                'flow_status' => $session->flow_status,
            ]);

            // 5. Transferir a agente si es necesario
            if ($session->flow_status === 'completed' && $bot->on_failure === 'assign_agent') {
                // $this->assignToAgent($session);
            }
        }
    }

    protected function processTextMessage(array $message, Contact $contact, WhatsappPhoneNumber $whatsappPhone): ?string
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
        ]);

        Log::channel('whatsapp')->info('Text message processed and saved.', [
            'message_id' => $messageRecord->message_id,
            'wa_id' => $message['id'],
            'content' => $textContent,
        ]);

        return $textContent; // Retorna el contenido
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

    protected function processFlowStep(
        ChatSession $session,
        string $messageContent
    ): void {
        try {
            $currentStep = $session->currentStep ?? $session->flow->initialStep;
            
            if (!$currentStep) {
                throw new \RuntimeException("No initial step defined for flow");
            }

            // Guardar respuesta si es paso de entrada
            if ($currentStep->type === 'input') {
                $this->saveUserResponse($session, $currentStep, $messageContent);
            }

            // Determinar siguiente paso
            $nextStep = $this->determineNextStep($currentStep, $messageContent);
            
            // Actualizar sesión
            $session->update([
                'current_step_id' => $nextStep?->step_id,
                'flow_status' => $nextStep ? 'in_progress' : 'completed'
            ]);

            // Enviar respuesta automática
            if ($nextStep && $nextStep->type !== 'input') {
                $this->sendStepResponse($nextStep, $session->contact);
            }

        } catch (\Exception $e) {
            Log::channel('flows')->error("Error processing flow step", [
                'session' => $session->id,
                'error' => $e->getMessage()
            ]);
            $session->update(['flow_status' => 'failed']);
        }
    }

    private function determineNextStep(
        ?FlowStep $currentStep,
        string $userInput
    ): ?FlowStep {
        if (!$currentStep) return null;

        // 1. Verificar si es paso terminal
        if ($currentStep->is_terminal) return null;

        // 2. Lógica de menús/buttons
        if ($currentStep->type === 'menu') {
            return $this->handleMenuTransition($currentStep, $userInput);
        }

        // 3. Transición lineal
        return $currentStep->nextStep ?? $currentStep->flow->steps()
            ->where('order', '>', $currentStep->order)
            ->orderBy('order')
            ->first();
    }

    private function handleMenuTransition(FlowStep $step, string $input): ?FlowStep {
        $selectedOption = collect($step->content['options'])
            ->first(fn($o) => $o['label'] === $input || $o['value'] === $input);

        return $selectedOption
            ? FlowStep::find($selectedOption['next_step_id'])
            : $step->flow->failure_step;
    }

    protected function determineFlow(WhatsappBot $bot, string $text): ?string
    {
        foreach ($bot->flows as $flow) {
            if ($flow->matchesTrigger($text)) {
                return $flow->flow_id;
            }
        }
        
        // A. Validar existencia del flujo por defecto
        if (!$bot->default_flow_id) {
            Log::channel('whatsapp')->error('Flujo por defecto no configurado para el bot', [
                'bot' => $bot->bot_name
            ]);
            return null;
        }
        
        // B. Verificar que el flujo exista
        $defaultFlowExists = Flow::where('flow_id', $bot->default_flow_id)->exists();
        if (!$defaultFlowExists) {
            Log::channel('whatsapp')->error('Flujo por defecto no existe', [
                'flow_id' => $bot->default_flow_id
            ]);
            return null;
        }
        
        return $bot->default_flow_id;
    }

    /**
     * Envía respuesta según tipo de paso
     */
    private function sendStepResponse(FlowStep $step, Contact $contact): void
    {
        $service = app(MessageDispatcherService::class);
        $phoneNumber = $step->flow->bots()->first()?->phoneNumber;

        if (!$phoneNumber) {
            Log::channel('flows')->error('No se encontró número de teléfono asociado al bot');
            return;
        }

        [$countryCode, $contactNumber] = $this->splitPhoneNumber($contact->full_phone);

        $service->sendTextMessage(
            $phoneNumber->phone_number_id, // phoneNumberId
            $countryCode,                  // countryCode
            $contactNumber,                // phoneNumber
            $step->content['text'],        // text
            false                          // previewUrl
        );
    }
}
