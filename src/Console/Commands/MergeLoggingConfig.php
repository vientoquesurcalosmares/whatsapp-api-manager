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
            // Verificar existencia del archivo
            if (!File::exists($projectConfigPath)) {
                $this->error("❌ Archivo logging.php no encontrado");
                return 1;
            }

            $configContent = File::get($projectConfigPath);

            // Verificar si el canal ya existe
            if (strpos($configContent, "'whatsapp'") === false) {
                $newContent = preg_replace(
                    "/(['\"]channels['\"]\s*=>\s*\[)/",
                    "$1\n{$channelConfig}",
                    $configContent
                );

                File::put($projectConfigPath, $newContent);
                $this->info("✅ Canal 'whatsapp' agregado exitosamente");
                return 0;
            }

            $this->info("ℹ️ El canal 'whatsapp' ya existe");
            return 0;

        } catch (\Exception $e) {
            $this->error("🔥 Error crítico: " . $e->getMessage());
            return 2;
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