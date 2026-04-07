<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class GenerateWhatsappKeys extends Command
{
    /**
     * El nombre y la firma del comando.
     */
    protected $signature = 'whatsapp:generate-keys 
                            {--force : Sobrescribe las llaves existentes sin preguntar} 
                            {--show : Muestra la llave pública en consola después de generar}
                            {--account-id= : ID de la cuenta WABA para guardar la clave en almacenamiento privado (multi-tenancy)}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Genera un nuevo par de llaves RSA de 2048 bits para WhatsApp Flows';

    /**
     * Ejecutar el comando.
     */
    public function handle()
    {
        $accountId = $this->option('account-id');
        
        if ($accountId) {
            $path = storage_path("app/whatsapp/flows/keys/{$accountId}");
        } else {
            $path = storage_path('app/public/whatsapp/flows/keys');
        }

        $privateKeyPath = "{$path}/private.pem";
        $publicKeyPath = "{$path}/public.pem";

        // 1. Validar existencia previa
        if (File::exists($privateKeyPath) && !$this->option('force')) {
            $confirmed = confirm(
                label: 'Ya existen llaves RSA en el storage. ¿Estás seguro de que deseas regenerarlas?',
                default: false,
                hint: 'Atención: Si regeneras las llaves, deberás subir la nueva llave pública a Meta o los flujos dejarán de funcionar.'
            );

            if (!$confirmed) {
                info('Operación cancelada.');
                return self::SUCCESS;
            }
        }

        // 2. Asegurar que el directorio existe
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // 3. Generar par de llaves mediante OpenSSL
        try {
            $publicKey = spin(function () use ($privateKeyPath, $publicKeyPath) {
                // Limpiar buffer de errores previos de OpenSSL
                while (openssl_error_string())
                    ;

                $config = [
                    "digest_alg" => "sha256",
                    "private_key_bits" => 2048,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                ];

                // --- Lógica Inteligente para Windows ---
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $opensslConf = null;

                    // 1. Prioridad: .env (limpiando posibles comillas)
                    $envPath = trim(env('OPENSSL_CONF', ''), '"\'');
                    if (!empty($envPath) && file_exists($envPath)) {
                        $opensslConf = $envPath;
                    } else {
                        // 2. Fallback: Detección automática basada en el binario de PHP
                        $phpDir = dirname(PHP_BINARY);
                        $pathsToTry = [
                            $phpDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
                            $phpDir . DIRECTORY_SEPARATOR . 'openssl.cnf',
                            'C:\laragon\bin\php\php-' . PHP_VERSION . '\extras\ssl\openssl.cnf',
                        ];

                        foreach ($pathsToTry as $p) {
                            if (file_exists($p)) {
                                $opensslConf = $p;
                                break;
                            }
                        }
                    }

                    if ($opensslConf) {
                        $config['config'] = $opensslConf;
                    }
                }

                // Crear el recurso de la llave
                $res = openssl_pkey_new($config);

                if (!$res) {
                    $sslError = "";
                    while ($msg = openssl_error_string()) {
                        $sslError .= $msg . " ";
                    }
                    throw new \Exception("OpenSSL falló al crear la llave: " . $sslError . " (Config Path: " . ($config['config'] ?? 'No detectada') . ")");
                }

                // Exportar llave privada
                $privateKey = null;
                if (!openssl_pkey_export($res, $privateKey, null, $config)) {
                    throw new \Exception("No se pudo exportar la llave privada. Verifica los permisos de escritura.");
                }

                // Extraer llave pública
                $publicKeyDetails = openssl_pkey_get_details($res);
                $pubKey = $publicKeyDetails["key"];

                // Guardar archivos físicamente
                File::put($privateKeyPath, $privateKey);
                File::put($publicKeyPath, $pubKey);

                return $pubKey;
            }, 'Generando par de llaves criptográficas...');

            info('✅ ¡Llaves generadas exitosamente!');
            $this->line("  🔑 Privada: <comment>{$privateKeyPath}</comment>");
            $this->line("  🔒 Pública: <comment>{$publicKeyPath}</comment>");

            if ($this->option('show')) {
                $this->newLine();
                info('Llave Pública (Copia esto para Meta):');
                $this->line($publicKey);
            }

            $this->newLine();
            warning('Próximo paso: Recuerda subir la nueva llave pública a Meta.');

        } catch (\Exception $e) {
            $this->newLine();
            error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}