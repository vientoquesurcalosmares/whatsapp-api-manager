<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Log;

class FlowMediaService
{
    /**
     * Descarga y desencripta un archivo proveniente de un WhatsApp Flow.
     * * @param array $mediaData El objeto que viene en el JSON del Flow (url, encryption_key, etc.)
     * @param string $subDirectory Directorio dentro de whatsapp/flows/media
     * @return array Datos del archivo guardado (path, url, name)
     */
    public function downloadAndDecrypt(array $mediaData, string $subDirectory = 'uploads'): array
    {
        $url = $mediaData['url'] ?? null;
        $encKey = $mediaData['encryption_key'] ?? null;
        $hmac = $mediaData['hmac'] ?? null;
        $iv = $mediaData['iv'] ?? null;

        if (!$url || !$encKey || !$iv) {
            throw new Exception("Datos de encriptación de medios incompletos.");
        }

        // 1. Descargar el binario encriptado
        $response = Http::get($url);
        if (!$response->successful()) {
            throw new Exception("No se pudo descargar el archivo desde Meta.");
        }

        $encryptedBinary = $response->body();

        // 2. Validar HMAC (Opcional pero recomendado para seguridad nivel enterprise)
        // Meta usa SHA256 para el HMAC
        $calculatedHmac = hash_hmac('sha256', $encryptedBinary, base64_decode($encKey), true);
        if (base64_encode($calculatedHmac) !== $hmac) {
            Log::channel('whatsapp')->error("HMAC Validation Failed for Flow Media");
            // throw new Exception("La integridad del archivo ha sido comprometida.");
        }

        // 3. Desencriptar usando AES-256-CBC
        $decryptedBinary = openssl_decrypt(
            $encryptedBinary,
            'aes-256-cbc',
            base64_decode($encKey),
            OPENSSL_RAW_DATA,
            base64_decode($iv)
        );

        if ($decryptedBinary === false) {
            throw new Exception("Fallo en la desencriptación AES del archivo.");
        }

        // 4. Guardar en Storage
        $fileName = Str::random(40);
        // Intentar adivinar extensión o usar bin por defecto (luego se puede mejorar con mime_content_type)
        $extension = $this->guessExtension($decryptedBinary);
        $fullName = "{$fileName}.{$extension}";

        $relativeDir = "whatsapp/flows/media/{$subDirectory}";
        $fullPath = "{$relativeDir}/{$fullName}";

        Storage::disk('public')->put($fullPath, $decryptedBinary);

        return [
            'path' => $fullPath,
            'url' => Storage::url($fullPath),
            'name' => $fullName,
            'mime' => $this->getMimeType($decryptedBinary)
        ];
    }

    protected function guessExtension($binary): string
    {
        $finfo = new \finfo(FILEINFO_EXTENSION);
        $ext = $finfo->buffer($binary);
        return $ext ? explode('/', $ext)[0] : 'file';
    }

    protected function getMimeType($binary): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($binary);
    }
}