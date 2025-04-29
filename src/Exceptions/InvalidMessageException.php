<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

use Exception, Throwable;

class InvalidMessageException extends Exception
{
    protected $code = 400;

    public function __construct(string $message = "", array $context = [], int $code = 0, Throwable $previous = null)
    {
        $fullMessage = $message . (empty($context) ? '' : ' Context: ' . json_encode($context));
        parent::__construct($fullMessage, $this->code, $previous);
    }

    public static function create(string $message, array $context = []): self
    {
        return new static($message, $context);
    }
}