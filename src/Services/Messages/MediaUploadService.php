<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Exceptions\MediaUploadException;

class MediaUploadService
{
    private const MAX_CHUNK_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 
        'image/png', 
        'video/mp4', 
        'audio/mpeg',
        'application/pdf'
    ];

    public function __construct(
        private ApiClient $apiClient,
        private string $version = 'v19.0'
    ) {}

    public function upload(
        string $filePath,
        string $mimeType,
        string $phoneNumberId
    ): string {
        $this->validateFile($filePath, $mimeType);

        $sessionId = $this->initUploadSession($filePath, $mimeType);
        $this->uploadFile($sessionId, $filePath);
        
        return $this->finalizeUpload($sessionId);
    }

    private function initUploadSession(string $filePath, string $mimeType): string
    {
        $query = http_build_query([
            'file_length' => filesize($filePath),
            'file_type' => $mimeType,
            'file_name' => basename($filePath)
        ]);

        $response = $this->apiClient->request(
            'POST',
            "{$this->version}/app/uploads?$query"
        );

        if (!isset($response['id'])) {
            throw new MediaUploadException(
                "Error al crear sesión de subida",
                ['response' => $response]
            );
        }

        return $response['id'];
    }

    private function uploadFile(string $sessionId, string $filePath): void
    {
        $handle = fopen($filePath, 'rb');
        $offset = 0;

        while (!feof($handle)) {
            $chunk = fread($handle, self::MAX_CHUNK_SIZE);
            $this->uploadChunk($sessionId, $chunk, $offset);
            $offset += strlen($chunk);
        }

        fclose($handle);
    }

    private function uploadChunk(string $sessionId, string $chunk, int $offset): void
    {
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Offset' => (string)$offset
        ];

        // Versión corregida usando solo parámetros necesarios
        $this->apiClient->request(
            'POST',
            "{$this->version}/{$sessionId}",
            headers: $headers,
            data: ['file_chunk' => $chunk] // Envía el chunk como cuerpo de la petición
        );
    }

    private function finalizeUpload(string $sessionId): string
    {
        $response = $this->apiClient->request(
            'GET',
            "{$this->version}/{$sessionId}"
        );

        if (!isset($response['h'])) {
            throw new MediaUploadException(
                "Error al finalizar subida",
                ['session_id' => $sessionId]
            );
        }

        return $response['h'];
    }

    private function validateFile(string $filePath, string $mimeType): void
    {
        if (!file_exists($filePath)) {
            throw new MediaUploadException("Archivo no encontrado: $filePath");
        }

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw MediaUploadException::invalidType($mimeType, self::ALLOWED_MIME_TYPES);
        }

        $maxSize = $this->getMaxSizeForType($mimeType);
        if (filesize($filePath) > $maxSize) {
            throw MediaUploadException::sizeExceeded(
                $this->getTypeName($mimeType), 
                $maxSize
            );
        }
    }

    private function getMaxSizeForType(string $mimeType): int
    {
        return match($mimeType) {
            'image/jpeg', 'image/png' => 5 * 1024 * 1024, // 5MB
            'video/mp4' => 16 * 1024 * 1024, // 16MB
            'audio/mpeg' => 16 * 1024 * 1024, // 16MB
            'application/pdf' => 100 * 1024 * 1024, // 100MB
            default => 0
        };
    }

    private function getTypeName(string $mimeType): string
    {
        return match($mimeType) {
            'image/jpeg', 'image/png' => 'imagen',
            'video/mp4' => 'video',
            'audio/mpeg' => 'audio',
            'application/pdf' => 'documento PDF',
            default => 'archivo'
        };
    }
}