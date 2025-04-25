<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi\DataTransferObjects;

class BusinessAccountResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $timezone,
        public readonly array $phoneNumbers,
        public readonly array $rawData
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            timezone: $data['timezone'] ?? 'UTC',
            phoneNumbers: $data['phone_numbers'] ?? [],
            rawData: $data
        );
    }
}