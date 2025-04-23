<?php

namespace ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct(
        string $message = "",
        int $code = 0,
        protected array $details = []
    ) {
        parent::__construct($message, $code);
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}