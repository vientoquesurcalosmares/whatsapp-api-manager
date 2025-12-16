<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

use Exception;
use Throwable;

class WhatsappApiException extends Exception
{
    protected array $details;

    public function __construct(string $message = "", int $code = 0, array $details = [], ?Throwable $previous = null)
    {
        $this->details = $details;
        parent::__construct($message, $code, $previous);
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}