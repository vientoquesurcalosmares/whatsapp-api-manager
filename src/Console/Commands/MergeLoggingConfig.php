<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MergeLoggingConfig extends Command
{
    protected $signature = 'whatsapp:merge-logging';
    protected $description = 'Agrega el canal de logs de WhatsApp al archivo existente';

    public function handle()
    {
        $projectConfigPath = config_path('logging.php');
        $channelConfig = $this->getChannelConfig();

        try {
            if (!File::exists($projectConfigPath)) {
                $this->error("❌ Archivo logging.php no encontrado");
                return 1;
            }

            $configContent = File::get($projectConfigPath);

            if (strpos($configContent, "'whatsapp'") === false) {
                $newContent = preg_replace(
                    "/(['\"]channels['\"]\s*=>\s*\[)([^\]]*)/",
                    "$1$2\n{$channelConfig}",
                    $configContent
                );

                if ($newContent === null) {
                    $this->error("❌ Error al modificar el archivo de configuración");
                    return 2;
                }

                File::put($projectConfigPath, $newContent);
                $this->info("✅ Canal 'whatsapp' agregado exitosamente");
                return 0;
            }

            $this->info("ℹ️ El canal 'whatsapp' ya existe");
            return 0;

        } catch (\Exception $e) {
            $this->error("🔥 Error crítico: " . $e->getMessage());
            return 3;
        }
    }

    private function getChannelConfig(): string
    {
        return <<<'EOD'

    'whatsapp' => [
        'driver' => 'daily',
        'path' => storage_path('logs/whatsapp.log'),
        'level' => 'debug',
        'days' => 7,
        'tap' => [\ScriptDevelop\WhatsappManager\Logging\CustomizeFormatter::class],
    ],
EOD;
    }
}