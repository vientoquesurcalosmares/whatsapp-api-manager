<?php

namespace ScriptDevelop\WhatsappManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Message;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $verifyToken = config('whatsapp-webhook.verify_token', env('WHATSAPP_VERIFY_TOKEN'));

        if ($request->isMethod('get') && $request->has(['hub_mode', 'hub_challenge', 'hub_verify_token'])) {
            if ($request->hub_mode === 'subscribe' && $request->hub_verify_token === $verifyToken) {
                return response($request->hub_challenge, 200);
            }
            return response()->json(['error' => 'Invalid token'], 403);
        }

        if ($request->isMethod('post')) {
            $payload = $request->all();
            Log::info('WhatsApp Webhook Payload:', $payload);

            if (isset($payload['entry'][0]['changes'][0]['value'])) {
                $value = $payload['entry'][0]['changes'][0]['value'];

                if (isset($value['messages'][0])) {
                    $message = $value['messages'][0];
                    $this->handleIncomingMessage($message, $value['contacts'][0] ?? null, $value['metadata'] ?? null);
                }

                if (isset($value['statuses'][0])) {
                    $status = $value['statuses'][0];
                    $this->handleStatusUpdate($status);
                }
            }

            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }

    protected function handleIncomingMessage(array $message, ?array $contact, ?array $metadata)
    {
        if ($message['type'] === 'text') {
            $phone = $message['from'];
            $text = $message['text']['body'] ?? '';

            // Aquí podrías normalizar el número de teléfono si quieres
            $phone = preg_replace('/[^0-9]/', '', $phone);

            // Procesar el contacto (buscarlo o crearlo en base de datos)
            $contactModel = config('whatsapp-webhook.contact_model', Contact::class);

            $contactRecord = $contactModel::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $contact['profile']['name'] ?? null,
                    'metadata' => $metadata ?? [],
                ]
            );

            // Procesar y guardar el mensaje recibido
            $messageModel = config('whatsapp-webhook.message_model', Message::class);

            $messageRecord = $messageModel::create([
                'contact_id' => $contactRecord->id,
                'message_id' => $message['id'],
                'from' => $phone,
                'text' => $text,
                'type' => $message['type'],
                'raw_payload' => $message, // Guarda el payload crudo si quieres
                'received_at' => now(),
            ]);

            Log::info('Saved incoming WhatsApp message for contact: ' . $phone);
        }
    }

    protected function handleStatusUpdate(array $status)
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;

        if (!$messageId || !$statusValue) {
            Log::warning('Missing status or message ID in status update', $status);
            return;
        }

        // Buscar el mensaje en la base de datos
        $messageModel = config('whatsapp-webhook.message_model', Message::class);

        $messageRecord = $messageModel::where('message_id', $messageId)->first();

        if (!$messageRecord) {
            Log::warning('Message not found for status update: ' . $messageId);
            return;
        }

        // Actualizar el estado del mensaje
        $messageRecord->status = $statusValue;
        $messageRecord->save();

        Log::info('Updated status for message ' . $messageId . ' to ' . $statusValue);
    }
}
