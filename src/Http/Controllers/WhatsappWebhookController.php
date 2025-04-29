<?php

namespace ScriptDevelop\WhatsappManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use ScriptDevelop\WhatsappManager\Enums\MessageStatus;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Conversation;
use ScriptDevelop\WhatsappManager\Models\Message;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\Helpers\CountryCodes;

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

        return response()->json(['error' => 'Invalid request method.'], 400);
    }

    protected function verifyWebhook(Request $request, string $verifyToken): Response|JsonResponse
    {
        if (
            $request->input('hub_mode') === 'subscribe' &&
            $request->input('hub_verify_token') === $verifyToken
        ) {
            return response()->make($request->input('hub_challenge'), 200);
        }

        return response()->json(['error' => 'Invalid verify token.'], 403);
    }

    protected function processIncomingMessage(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('Received WhatsApp Webhook Payload:', $payload);

        $value = data_get($payload, 'entry.0.changes.0.value');

        if (!$value) {
            Log::warning('No value found in webhook payload.', $payload);
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
        if (($message['type'] ?? '') !== 'text') {
            return;
        }

        Log::warning('Handle Incoming Message: ', [
            'message' => $message,
            'contact' => $contact,
            'metadata' => $metadata,
        ]);

        if (empty($contact['wa_id'])) {
            Log::warning('No wa_id found in contact.', $contact ?? []);
            return;
        }

        $fullPhone = preg_replace('/\D/', '', $message['from'] ?? '');

        [$countryCode, $phoneNumber] = $this->splitPhoneNumber($fullPhone);

        if (empty($countryCode) || empty($phoneNumber)) {
            Log::warning('Unable to split phone number.', ['fullPhone' => $fullPhone]);
            return;
        }

        if (empty($fullPhone)) {
            Log::warning('Incoming message without a valid phone number.', $message);
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
            Log::error('No matching WhatsappPhoneNumber found for api_phone_number_id.', [
                'api_phone_number_id' => $apiPhoneNumberId,
            ]);
            return;
        }

        $message_saved = Message::create([
            'whatsapp_phone_id' => $whatsappPhone->phone_number_id,
            'contact_id' => $contactRecord->contact_id,
            'wa_id' => $message['id'] ?? null,
            'message_from' => $contact['wa_id'],
            'message_to' => $whatsappPhone->country_code.$whatsappPhone->phone_number,
            'message_content' => $message['text']['body'] ?? '',
            'message_type' => $message['type'],
            'json_content' => $message,
            'json' => json_encode($message),
            'messaging_product' => 'whatsapp',
            'status' => MessageStatus::RECEIVED,
            // 'received_at' => now(),
        ]);

        Log::info('Message saved. ', [
            'message' => $message_saved
        ]);
    }

    protected function handleStatusUpdate(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;
        $timestamp = $status['timestamp'] ?? null;

        if (empty($messageId) || empty($statusValue)) {
            Log::warning('Missing message ID or status in status update.', $status);
            return;
        }

        $messageRecord = Message::where('wa_id', $messageId)->first();

        if (!$messageRecord) {
            Log::warning('Message record not found for status update.', ['wa_id' => $messageId]);
            return;
        }

        // 1. Actualizar estado del mensaje
        $this->updateMessageStatus($messageRecord, $statusValue, $timestamp);
        
        // 2. Procesar datos de conversación y métricas
        if (isset($status['conversation'])) {
            $this->processConversationData($messageRecord, $status);
        }

        Log::info('Estado actualizado', [
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

        $conversation = Conversation::updateOrCreate(
            ['wa_conversation_id' => $conversationData['id']],
            [
                'expiration_timestamp' => isset($conversationData['expiration_timestamp']) 
                    ? \Carbon\Carbon::createFromTimestamp($conversationData['expiration_timestamp'])
                    : null,
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
}
