<?php

namespace ScriptDevelop\WhatsappManager\Services\Messages;

use ScriptDevelop\WhatsappManager\Enums\MessageStatus;
use ScriptDevelop\WhatsappManager\Models\Message;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Exceptions\InvalidMessageException;

abstract class BaseMessageDispatcher
{
    protected ApiClient $apiClient;
    protected string $version;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->version = config('whatsapp.api.version', 'v19.0');
    }

    protected function sendMessage(
        array $payload,
        string $phoneNumberId
    ): Message {
        $this->validatePayloadStructure($payload);

        try {
            $endpoint = Endpoints::build(Endpoints::SEND_MESSAGE, [
                'phone_number_id' => $phoneNumberId
            ]);

            $response = $this->apiClient->request('POST', $endpoint, data: $payload);

            return $this->createMessageRecord($payload, $phoneNumberId, $response);
        } catch (\Exception $e) {
            return $this->handleApiError($e, $payload, $phoneNumberId);
        }
    }

    private function validatePayloadStructure(array $payload): void
    {
        $requiredKeys = ['messaging_product', 'to', 'type'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new InvalidMessageException("Falta campo requerido: $key", $payload);
            }
        }
    }

    private function createMessageRecord(array $payload, string $phoneNumberId, array $response): Message
    {
        return Message::create([
            'wa_id' => $response['messages'][0]['id'] ?? null,
            'whatsapp_phone_id' => $phoneNumberId,
            'message_type' => $payload['type'],
            'message_content' => $this->extractContent($payload),
            'json_content' => $payload,
            'status' => MessageStatus::SENT,
            'sent_at' => now(),
            'context_message_id' => $payload['context']['message_id'] ?? null
        ]);
    }

    protected function handleApiError(\Exception $e, array $payload, string $phoneNumberId): Message
    {
        return Message::create([
            'whatsapp_phone_id' => $phoneNumberId,
            'message_type' => $payload['type'] ?? 'unknown',
            'message_content' => $this->extractContent($payload) ?? 'Error en el mensaje',
            'json_content' => $payload,
            'status' => MessageStatus::FAILED,
            'failed_at' => now(),
            'error_details' => $e->getMessage(),
            'code_error' => $e->getCode()
        ]);
    }

    abstract protected function extractContent(array $payload): string;
}