<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

use Exception;
use Throwable; // Importación necesaria

class InvalidApiResponseException extends Exception
{
    protected array $details;

    // Constructor corregido con tipo Throwable importado
    public function __construct(
        string $message = "",
        int $code = 0,
        array $details = [],
        Throwable $previous = null // Ahora sí reconoce Throwable
    ) {
        $this->details = $details;
        parent::__construct($message, $code, $previous);
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    // Método estático mejorado
    public static function fromValidationError(string $message, array $errors): self
    {
        return new self(
            "Invalid API response structure: $message",
            422,
            ['validation_errors' => $errors]
        );
    }
}