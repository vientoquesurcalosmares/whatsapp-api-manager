<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

use Exception;
use Throwable;

class MediaUploadException extends Exception
{
    protected $code = 415; // HTTP Unsupported Media Type

    public static function invalidType(string $type, array $allowed): self
    {
        return new static(
            "Tipo de archivo no permitido: $type. Formatos válidos: " . implode(', ', $allowed),
            ['allowed_types' => $allowed]
        );
    }

    public static function sizeExceeded(string $type, int $maxSize): self
    {
        return new static(
            "Tamaño máximo excedido para $type: " . self::formatBytes($maxSize),
            ['max_size' => $maxSize]
        );
    }

    public static function fileNotFound(string $path): self
    {
        return new static("Archivo no encontrado: $path");
    }

    public function __construct(
        string $message = "",
        array $context = [],
        int $code = 0,
        Throwable $previous = null
    ) {
        $fullMessage = $message;
        if (!empty($context)) {
            $fullMessage .= " [Detalles: " . json_encode($context) . "]";
        }

        parent::__construct($fullMessage, $this->code, $previous);
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }
        
        return round($bytes, 2) . ' ' . $units[$index];
    }
}