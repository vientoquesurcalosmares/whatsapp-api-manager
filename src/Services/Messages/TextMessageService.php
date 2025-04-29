<?php

namespace ScriptDevelop\WhatsappManager\Services\Messages;

use ScriptDevelop\WhatsappManager\Models\Message;
use ScriptDevelop\WhatsappManager\Exceptions\InvalidMessageException;

class TextMessageService extends BaseMessageDispatcher
{
    public function send(
        string $to,
        string $content,
        bool $previewUrl = false,
        ?string $replyTo = null,
        string $phoneNumberId
    ): Message {
        $this->validateInput($content, $to);

        $payload = $this->buildPayload($to, $content, $previewUrl, $replyTo);

        return parent::sendMessage($payload, $phoneNumberId);
    }

    protected function extractContent(array $payload): string
    {
        return $payload['text']['body'];
    }

    private function buildPayload(
        string $to,
        string $content,
        bool $previewUrl,
        ?string $replyTo
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $content,
                'preview_url' => $previewUrl
            ]
        ];

        if ($replyTo) {
            $payload['context'] = ['message_id' => $replyTo];
        }

        return $payload;
    }

    private function validateInput(string $content, string $to): void
    {
        $this->validateContentLength($content);
        $this->validatePhoneNumber($to);
    }

    private function validateContentLength(string $content): void
    {
        if (mb_strlen($content) > 4096) {
            throw new InvalidMessageException("Límite de 4096 caracteres excedido", [
                'content_length' => mb_strlen($content)
            ]);
        }
    }

    private function validatePhoneNumber(string $to): void
    {
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $to)) {
            throw new InvalidMessageException("Formato de número inválido", [
                'input' => $to,
                'pattern' => '/^\+?[1-9]\d{1,14}$/'
            ]);
        }
    }
}