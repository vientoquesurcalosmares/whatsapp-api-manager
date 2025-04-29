<?php

namespace ScriptDevelop\WhatsappManager\Exceptions;

class InvalidMediaException extends InvalidMessageException
{
    protected $code = 415;
    
    public static function invalidType(string $type, array $allowed): self
    {
        return new static(
            "Tipo de archivo no permitido: $type. Tipos válidos: " . implode(', ', $allowed),
            ['allowed_types' => $allowed]
        );
    }

    public static function sizeExceeded(string $type, int $maxSize): self
    {
        return new static(
            "Tamaño excede el límite para $type: " . self::formatBytes($maxSize), // Usar self::
            ['max_size' => $maxSize]
        );
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