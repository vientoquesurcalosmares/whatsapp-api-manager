<?php

namespace ScriptDevelop\WhatsappManager\Services\Messages;

use ScriptDevelop\WhatsappManager\Exceptions\InvalidMediaException;
use ScriptDevelop\WhatsappManager\Models\Message;

class ImageMessageService extends BaseMessageDispatcher
{
    private const MAX_CAPTION_LENGTH = 1024;

    public function send(
        string $to,
        string $mediaIdOrUrl,
        bool $isUrl = false,
        ?string $caption = null,
        ?string $replyTo = null,
        string $phoneNumberId
    ): Message {
        $this->validateInputs($caption);

        $payload = $this->buildPayload(
            to: $to,
            mediaIdOrUrl: $mediaIdOrUrl,
            isUrl: $isUrl,
            caption: $caption,
            replyTo: $replyTo
        );

        return parent::sendMessage($payload, $phoneNumberId);
    }

    protected function extractContent(array $payload): string
    {
        return $payload['image']['caption'] ?? '';
    }

    private function buildPayload(
        string $to,
        string $mediaIdOrUrl,
        bool $isUrl,
        ?string $caption,
        ?string $replyTo
    ): array {
        $imagePayload = $isUrl 
            ? ['link' => $mediaIdOrUrl]
            : ['id' => $mediaIdOrUrl];

        if ($caption) {
            $imagePayload['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'image',
            'image' => $imagePayload
        ];

        if ($replyTo) {
            $payload['context'] = ['message_id' => $replyTo];
        }

        return $payload;
    }

    private function validateInputs(?string $caption): void
    {
        if ($caption && mb_strlen($caption) > self::MAX_CAPTION_LENGTH) {
            throw new InvalidMediaException(
                "El pie de foto no puede exceder " . self::MAX_CAPTION_LENGTH . " caracteres",
                ['length' => mb_strlen($caption)]
            );
        }
    }
}