<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows;

use Illuminate\Support\Facades\File;
use Exception;

class FlowCryptoService
{
    protected string $privateKey;

    public function __construct()
    {
        $path = storage_path('app/public/whatsapp/flows/keys/private.pem');

        if (!File::exists($path)) {
            throw new Exception("No se encontró la llave privada en: {$path}. Ejecuta php artisan whatsapp:generate-keys");
        }

        $this->privateKey = File::get($path);
    }

    /**
     * Desencripta la petición entrante de WhatsApp Flows.
     */
    public function decryptRequest(string $encryptedAesKey, string $encryptedFlowData, string $initialVector): array
    {
        // 1. Desencriptar la llave AES usando RSA con la llave privada
        $decryptedAesKey = null;
        $privateKeyResource = openssl_pkey_get_private($this->privateKey);

        if (!openssl_private_decrypt(base64_decode($encryptedAesKey), $decryptedAesKey, $privateKeyResource, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new Exception("Fallo al desencriptar la llave AES: " . openssl_error_string());
        }

        // 2. Desencriptar los datos del flujo usando AES-128-GCM
        $encryptedDataBinary = base64_decode($encryptedFlowData);
        $iv = base64_decode($initialVector);

        // El tag de autenticación son los últimos 16 bytes en AES-GCM
        $tag = substr($encryptedDataBinary, -16);
        $ciphertext = substr($encryptedDataBinary, 0, -16);

        $decryptedData = openssl_decrypt(
            $ciphertext,
            'aes-128-gcm',
            $decryptedAesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decryptedData === false) {
            throw new Exception("Fallo al desencriptar los datos del flujo.");
        }

        return json_decode($decryptedData, true);
    }

    /**
     * Encripta la respuesta para enviarla de vuelta a WhatsApp.
     */
    public function encryptResponse(array $data, string $aesKey, string $iv): string
    {
        $plainText = json_encode($data);
        $tag = null;

        $ciphertext = openssl_encrypt(
            $plainText,
            'aes-128-gcm',
            base64_decode($aesKey), // Usamos la misma llave que nos mandaron
            OPENSSL_RAW_DATA,
            base64_decode($iv),
            $tag
        );

        // La respuesta debe ser base64(ciphertext + tag)
        return base64_encode($ciphertext . $tag);
    }
}