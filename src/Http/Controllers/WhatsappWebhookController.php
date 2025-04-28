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
            if ($request->hub_mode === 'subscribe' && $request->hub_verify_token === $verifyToken) {
                return response()->make($request->input('hub_challenge'), 200);
            }
            return response()->json(['error' => 'Invalid token'], 403);
        }

        if ($request->isMethod('post')) {
            $payload = $request->all();
            Log::info('WhatsApp Webhook Payload:', $payload);

            if (isset($payload['entry'][0]['changes'][0]['value'])) {
                $value = $payload['entry'][0]['changes'][0]['value'];

                if (isset($value['messages'][0])) {
                    $message = $value['messages'][0] ?? [];
                    $contact = $value['contacts'][0] ?? null;
                    $metadata = $value['metadata'] ?? null;

                    $this->handleIncomingMessage($message, $contact, $metadata);
                }

                if (isset($value['statuses'][0])) {
                    $status = $value['statuses'][0] ?? [];
                    $this->handleStatusUpdate($status);
                }
            }

            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Invalid request method.'], 400);
    }

    protected function handleIncomingMessage(array $message, ?array $contact, ?array $metadata): void
    {
        if (($message['type'] ?? null) === 'text') {
            $fullPhone = preg_replace('/\D/', '', $message['from'] ?? '');

            if (!$fullPhone) {
                Log::warning('Incoming message without valid phone number.', $message);
                return;
            }

            // Dividir el número en country code y phone number
            [$countryCode, $phoneNumber] = $this->splitPhoneNumber($fullPhone);

            if (!$countryCode || !$phoneNumber) {
                Log::warning('Could not split phone number.', ['fullPhone' => $fullPhone]);
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

            Log::info('Saved incoming WhatsApp message for contact: +' . $countryCode . ' ' . $phoneNumber);
        }
    }

    protected function handleStatusUpdate(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;

        if (!$messageId || !$statusValue) {
            Log::warning('Missing message ID or status value in status update.', $status);
            return;
        }

        $messageRecord = Message::where('message_id', $messageId)->first();

        if (!$messageRecord) {
            Log::warning('Message not found for status update: ' . $messageId);
            return;
        }

        $messageRecord->status = $statusValue;
        $messageRecord->save();

        Log::info('Updated status for message ID: ' . $messageId . ' to status: ' . $statusValue);
    }

    private function splitPhoneNumber(string $fullPhone): array
    {
        $codes = CountryCodes::list();

        // Ordenamos por longitud descendente para detectar bien los códigos de 3 dígitos primero
        usort($codes, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($fullPhone, $code)) {
                $phoneNumber = substr($fullPhone, strlen($code));
                return [$code, $phoneNumber];
            }
        }

        // No encontró coincidencia
        return [null, null];
    }
}
