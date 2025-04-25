<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi\DataTransferObjects;

use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;

class ApiErrorResponse
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array $details,
        public readonly ?ApiException $exception
    ) {}

    public static function fromException(ApiException $e): self
    {
        return new self(
            code: (string) $e->getCode(),
            message: $e->getMessage(),
            details: $e->getDetails(),
            exception: $e
        );
    }
}