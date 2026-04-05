<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Maneja la descarga y desencriptación de archivos multimedia
 * enviados a través de WhatsApp Flows (PhotoPicker / DocumentPicker).
 *
 * Algoritmo según spec de Meta:
 *   cdn_file  = ciphertext || hmac10
 *   1. SHA256(cdn_file)          == encrypted_hash  (valida integridad del archivo CDN)
 *   2. hmac10                    == HMAC-SHA256(hmac_key, iv || ciphertext)[0:10]
 *   3. decrypted                 = AES-256-CBC(encryption_key, iv, ciphertext) + pkcs7 unpad
 *   4. SHA256(decrypted)         == plaintext_hash  (valida integridad del archivo descifrado)
 */
class FlowMediaService
{
    /**
     * Procesa un ítem de photo_picker / document_picker proveniente del nfm_reply.
     *
     * El webhook solo trae { file_name, mime_type, sha256, id }.
     * Primero obtenemos los metadatos de encriptación desde la API de Meta,
     * luego descargamos, desencriptamos y validamos el archivo.
     *
     * @param array  $mediaItem     Ítem del array photo_picker / document_picker del nfm_reply
     * @param Model  $whatsappPhone Modelo del número de teléfono (necesario para el token de API)
     * @param string $subDirectory  Subdirectorio dentro de whatsapp/flows/media/
     * @return array { path, url, name, mime, file_name, media_id }
     */
    public function processFlowMedia(array $mediaItem, Model $whatsappPhone, string $subDirectory = 'uploads'): array
    {
        $mediaId = $mediaItem['id'] ?? null;

        if (! $mediaId) {
            throw new Exception('media_id ausente en el ítem de media del Flow.');
        }

        // 1. Obtener metadatos de encriptación desde Meta Graph API
        $metaData = $this->fetchMediaMetadata($mediaId, $whatsappPhone);

        $cdnUrl            = $metaData['cdn_url']                              ?? null;
        $encMeta           = $metaData['encryption_metadata']                  ?? [];
        $encryptedHash     = $encMeta['encrypted_hash']                        ?? null;
        $iv                = $encMeta['iv']                                    ?? null;
        $encryptionKey     = $encMeta['encryption_key']                        ?? null;
        $hmacKey           = $encMeta['hmac_key']                              ?? null;
        $plaintextHash     = $encMeta['plaintext_hash']                        ?? null;

        if (! $cdnUrl || ! $iv || ! $encryptionKey || ! $hmacKey) {
            throw new Exception("Metadatos de encriptación incompletos para media_id: {$mediaId}");
        }

        // 2. Descargar cdn_file (ciphertext || hmac10)
        $cdnFile = $this->downloadCdnFile($cdnUrl);

        // 3. Validar SHA256(cdn_file) == encrypted_hash
        if ($encryptedHash) {
            $calculatedHash = base64_encode(hash('sha256', $cdnFile, true));
            if (! hash_equals($encryptedHash, $calculatedHash)) {
                throw new Exception("Fallo en validación de hash del archivo CDN para media_id: {$mediaId}");
            }
        }

        // 4. Separar ciphertext y hmac10 (últimos 10 bytes)
        $fileLength = strlen($cdnFile);
        if ($fileLength <= 10) {
            throw new Exception("Archivo CDN demasiado pequeño para media_id: {$mediaId}");
        }

        $ciphertext = substr($cdnFile, 0, $fileLength - 10);
        $hmac10     = substr($cdnFile, $fileLength - 10);

        // 5. Validar HMAC: HMAC-SHA256(hmac_key, iv || ciphertext)[0:10] == hmac10
        $hmacKeyBin  = base64_decode($hmacKey);
        $ivBin       = base64_decode($iv);
        $fullHmac    = hash_hmac('sha256', $ivBin . $ciphertext, $hmacKeyBin, true);
        $expectedHmac10 = substr($fullHmac, 0, 10);

        if (! hash_equals($expectedHmac10, $hmac10)) {
            throw new Exception("Fallo en validación HMAC del archivo para media_id: {$mediaId}");
        }

        // 6. Desencriptar con AES-256-CBC + pkcs7 unpadding automático
        $encryptionKeyBin = base64_decode($encryptionKey);
        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $encryptionKeyBin,
            OPENSSL_RAW_DATA,
            $ivBin
        );

        if ($decrypted === false) {
            throw new Exception("Fallo en la desencriptación AES para media_id: {$mediaId}");
        }

        // 7. Validar SHA256(decrypted) == plaintext_hash
        if ($plaintextHash) {
            $decryptedHash = base64_encode(hash('sha256', $decrypted, true));
            if (! hash_equals($plaintextHash, $decryptedHash)) {
                throw new Exception("Fallo en validación de hash del archivo descifrado para media_id: {$mediaId}");
            }
        }

        // 8. Guardar en Storage
        return $this->storeDecryptedFile(
            $decrypted,
            $mediaItem['file_name'] ?? null,
            $mediaItem['mime_type'] ?? null,
            $subDirectory,
            $mediaId
        );
    }

    /**
     * Procesa un ítem que ya viene con cdn_url y encryption_metadata completos
     * (caso del data exchange endpoint, no del nfm_reply final).
     *
     * @param array  $mediaItem    { cdn_url, file_name, encryption_metadata: {...} }
     * @param string $subDirectory
     * @return array { path, url, name, mime, file_name, media_id }
     */
    public function processInlineMedia(array $mediaItem, string $subDirectory = 'uploads'): array
    {
        $cdnUrl        = $mediaItem['cdn_url']                                     ?? null;
        $encMeta       = $mediaItem['encryption_metadata']                         ?? [];
        $encryptedHash = $encMeta['encrypted_hash']                                ?? null;
        $iv            = $encMeta['iv']                                            ?? null;
        $encryptionKey = $encMeta['encryption_key']                                ?? null;
        $hmacKey       = $encMeta['hmac_key']                                      ?? null;
        $plaintextHash = $encMeta['plaintext_hash']                                ?? null;
        $mediaId       = $mediaItem['media_id']                                    ?? null;

        if (! $cdnUrl || ! $iv || ! $encryptionKey || ! $hmacKey) {
            throw new Exception('Metadatos de encriptación incompletos en el ítem inline.');
        }

        $cdnFile = $this->downloadCdnFile($cdnUrl);

        if ($encryptedHash) {
            $calculatedHash = base64_encode(hash('sha256', $cdnFile, true));
            if (! hash_equals($encryptedHash, $calculatedHash)) {
                throw new Exception('Fallo en validación de hash del archivo CDN (inline).');
            }
        }

        $fileLength = strlen($cdnFile);
        if ($fileLength <= 10) {
            throw new Exception('Archivo CDN demasiado pequeño (inline).');
        }

        $ciphertext     = substr($cdnFile, 0, $fileLength - 10);
        $hmac10         = substr($cdnFile, $fileLength - 10);

        $hmacKeyBin     = base64_decode($hmacKey);
        $ivBin          = base64_decode($iv);
        $fullHmac       = hash_hmac('sha256', $ivBin . $ciphertext, $hmacKeyBin, true);
        $expectedHmac10 = substr($fullHmac, 0, 10);

        if (! hash_equals($expectedHmac10, $hmac10)) {
            throw new Exception('Fallo en validación HMAC del archivo (inline).');
        }

        $encryptionKeyBin = base64_decode($encryptionKey);
        $ivBin            = base64_decode($iv);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $encryptionKeyBin,
            OPENSSL_RAW_DATA,
            $ivBin
        );

        if ($decrypted === false) {
            throw new Exception('Fallo en la desencriptación AES (inline).');
        }

        if ($plaintextHash) {
            $decryptedHash = base64_encode(hash('sha256', $decrypted, true));
            if (! hash_equals($plaintextHash, $decryptedHash)) {
                throw new Exception('Fallo en validación de hash del archivo descifrado (inline).');
            }
        }

        return $this->storeDecryptedFile(
            $decrypted,
            $mediaItem['file_name'] ?? null,
            null,
            $subDirectory,
            $mediaId
        );
    }

    /**
     * Llama a Meta Graph API para obtener los metadatos de encriptación de un media_id.
     * Retorna: { cdn_url, encryption_metadata: { encrypted_hash, iv, encryption_key, hmac_key, plaintext_hash } }
     */
    protected function fetchMediaMetadata(string $mediaId, Model $whatsappPhone): array
    {
        $baseUrl = rtrim(config('whatsapp.api.base_url', env('WHATSAPP_API_URL')), '/');
        $version = config('whatsapp.api.version', env('WHATSAPP_API_VERSION'));
        $token   = $whatsappPhone->businessAccount->api_token;

        $url = "{$baseUrl}/{$version}/{$mediaId}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get($url);

        if (! $response->successful()) {
            throw new Exception("No se pudo obtener metadatos de media_id {$mediaId} desde Meta API. HTTP {$response->status()}");
        }

        $data = $response->json();

        if (empty($data['cdn_url']) || empty($data['encryption_metadata'])) {
            throw new Exception("Respuesta inesperada de Meta API para media_id {$mediaId}: " . json_encode($data));
        }

        return $data;
    }

    /**
     * Descarga el archivo binario del CDN de WhatsApp (no requiere token).
     */
    protected function downloadCdnFile(string $cdnUrl): string
    {
        $response = Http::withOptions(['verify' => true])->get($cdnUrl);

        if (! $response->successful()) {
            throw new Exception("No se pudo descargar el archivo desde WhatsApp CDN. HTTP {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Guarda el binario descifrado en Storage y retorna los datos del archivo.
     */
    protected function storeDecryptedFile(
        string  $binary,
        ?string $originalFileName,
        ?string $mimeType,
        string  $subDirectory,
        ?string $mediaId
    ): array {
        // Detectar extensión: prioridad al mime_type original, luego detección automática
        if ($mimeType) {
            $ext = $this->getFileExtensionFromMime($mimeType);
        } else {
            $ext = $this->detectExtensionFromBinary($binary);
        }

        // Generar nombre de archivo: conservar nombre original si existe
        if ($originalFileName) {
            $baseName  = pathinfo($originalFileName, PATHINFO_FILENAME);
            $safeName  = Str::slug($baseName) ?: Str::random(20);
            $fileName  = $safeName . '_' . Str::random(8) . '.' . $ext;
        } else {
            $fileName = Str::random(40) . '.' . $ext;
        }

        $relativeDir  = "whatsapp/flows/media/{$subDirectory}";
        $fullPath     = "{$relativeDir}/{$fileName}";

        Storage::disk('public')->put($fullPath, $binary);

        // Detectar mime real del binario descifrado
        $detectedMime = $mimeType ?: $this->detectMimeFromBinary($binary);

        return [
            'path'          => $fullPath,
            'url'           => Storage::url($fullPath),
            'name'          => $fileName,
            'original_name' => $originalFileName,
            'mime'          => $detectedMime,
            'size'          => strlen($binary),
            'media_id'      => $mediaId,
        ];
    }

    protected function getFileExtensionFromMime(string $mimeType): string
    {
        // Normalizar (ej: "image/jpeg; charset=..." → "image/jpeg")
        if (str_contains($mimeType, ';')) {
            $mimeType = trim(explode(';', $mimeType)[0]);
        }

        return match ($mimeType) {
            'image/jpeg'                                                                  => 'jpg',
            'image/png'                                                                   => 'png',
            'image/webp'                                                                  => 'webp',
            'image/gif'                                                                   => 'gif',
            'image/heic', 'image/heif'                                                   => 'heic',
            'video/mp4', 'video/3gpp'                                                    => 'mp4',
            'video/quicktime'                                                             => 'mov',
            'audio/mpeg', 'audio/mp3'                                                    => 'mp3',
            'audio/ogg', 'audio/ogg; codecs=opus'                                        => 'ogg',
            'audio/aac'                                                                   => 'aac',
            'application/pdf'                                                             => 'pdf',
            'application/msword'                                                          => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/vnd.ms-excel'                                                   => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-powerpoint'                                              => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain'                                                                  => 'txt',
            'text/csv'                                                                    => 'csv',
            default                                                                       => 'bin',
        };
    }

    protected function detectExtensionFromBinary(string $binary): string
    {
        $finfo = new \finfo(FILEINFO_EXTENSION);
        $raw   = $finfo->buffer($binary);
        if (! $raw || $raw === '???') {
            return 'bin';
        }
        return explode('/', $raw)[0];
    }

    protected function detectMimeFromBinary(string $binary): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($binary) ?: 'application/octet-stream';
    }
}
