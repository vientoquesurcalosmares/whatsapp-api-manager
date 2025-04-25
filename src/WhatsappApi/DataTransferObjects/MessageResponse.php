<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi\DataTransferObjects;

use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;

class MessageResponse
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $status,
        // Campos adicionales según respuesta real de WhatsApp
        public readonly ?string $recipientId = null,
        public readonly ?string $timestamp = null,
        public readonly array $originalResponse = [],
        public readonly ?ApiException $error = null
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            messageId: $data['id'] ?? '',
            status: $data['status'] ?? 'failed',
            recipientId: $data['to'] ?? null,
            timestamp: $data['timestamp'] ?? null,
            originalResponse: $data
        );
    }

    public static function fromError(ApiException $exception): self
    {
        return new self(
            messageId: '',
            status: 'error',
            error: $exception,
            originalResponse: $exception->getDetails()
        );
    }

    public function isSuccess(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }
}