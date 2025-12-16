<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

use Exception;
use Illuminate\Support\Facades\Lang;
use Illuminate\Http\JsonResponse;

class ValidationException extends Exception
{
    /**
     * @var array Errores de validación
     */
    protected $errors = [];

    /**
     * @var int Código de estado HTTP
     */
    protected $statusCode = 422;

    public function __construct(
        ?string $message = null,
        array $errors = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct(
            $message ?? Lang::get('whatsapp-manager::validation.generic'),
            $code,
            $previous
        );

        $this->errors = $errors;
    }

    /**
     * Crea una excepción para un campo específico
     */
    public static function forField(
        string $field,
        string $message,
        array $params = []
    ): self {
        return new static(
            Lang::get('whatsapp-manager::validation.field', [
                'field' => $field,
                'message' => Lang::get('whatsapp-manager::validation.' . $message, $params)
            ]),
            [$field => [Lang::get('whatsapp-manager::validation.' . $message, $params)]]
        );
    }

    /**
     * Convierte la excepción a respuesta HTTP
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'error_code' => $this->getCode(),
            'message' => $this->getMessage(),
            'errors' => $this->getErrors()
        ], $this->statusCode);
    }

    /**
     * Obtiene los errores de validación
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtiene el código de estado HTTP
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Define el código de estado HTTP
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }
}