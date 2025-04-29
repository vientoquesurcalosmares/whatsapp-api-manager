<?php

namespace ScriptDevelop\WhatsappManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Message;
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

        $fullPhone = preg_replace('/\D/', '', $message['from'] ?? '');

        if (empty($fullPhone)) {
            Log::warning('Incoming message without a valid phone number.', $message);
            return;
        }

        [$countryCode, $phoneNumber] = $this->splitPhoneNumber($fullPhone);

        if (empty($countryCode) || empty($phoneNumber)) {
            Log::warning('Unable to split phone number.', ['fullPhone' => $fullPhone]);
            return;
        }

        $contactRecord = Contact::firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'country_code' => $countryCode,
                'name' => $contact['profile']['name'] ?? null,
                'metadata' => $metadata ?? [],
            ]
        );

        $contactRecord->update([
            'name' => $contact['profile']['name'] ?? $contactRecord->name,
            'metadata' => $metadata ?? $contactRecord->metadata,
        ]);

        Message::create([
            'contact_id' => $contactRecord->id,
            'message_id' => $message['id'] ?? null,
            'from' => $phoneNumber,
            'country_code' => $countryCode,
            'text' => $message['text']['body'] ?? '',
            'type' => $message['type'],
            'raw_payload' => json_encode($message),
            'received_at' => now(),
        ]);

        Log::info('Saved incoming WhatsApp message for contact.', [
            'country_code' => $countryCode,
            'phone_number' => $phoneNumber,
        ]);
    }

    protected function handleStatusUpdate(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;

        if (empty($messageId) || empty($statusValue)) {
            Log::warning('Missing message ID or status in status update.', $status);
            return;
        }

        $messageRecord = Message::where('message_id', $messageId)->first();

        if (!$messageRecord) {
            Log::warning('Message record not found for status update.', ['message_id' => $messageId]);
            return;
        }

        $messageRecord->update(['status' => $statusValue]);

        Log::info('Updated message status.', [
            'message_id' => $messageId,
            'status' => $statusValue,
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
}
