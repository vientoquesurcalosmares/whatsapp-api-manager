<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MergeLoggingConfig extends Command
{
    protected $signature = 'whatsapp:merge-logging';
    protected $description = 'Fusiona la configuración de logs del paquete';

    public function handle()
    {
        $packageConfigPath = __DIR__.'/../../../config/logging.php';
        $projectConfigPath = config_path('logging.php');

        try {
            if (!File::exists($projectConfigPath)) {
                File::copy($packageConfigPath, $projectConfigPath);
                $this->info('Auto-creado logging.php');
                return 0;
            }

            $projectConfig = File::get($projectConfigPath);
            if (strpos($projectConfig, "'whatsapp'") === false) {
                $newConfig = preg_replace(
                    "/(\'channels\' => \[)/",
                    "$1\n\t'whatsapp' => [\n\t\t'driver' => 'daily',\n\t\t'path' => storage_path('logs/whatsapp.log'),\n\t\t'level' => 'debug',\n\t\t'days' => 7,\n\t],",
                    $projectConfig
                );
                File::put($projectConfigPath, $newConfig);
                $this->info('Auto-agregado canal whatsapp');
            }
            return 0;
        } catch (\Exception $e) {
            $this->error("Error automático: {$e->getMessage()}");
            return 1;
        }
    }

    private function channelExists($configContent): bool
    {
        return strpos($configContent, "'whatsapp'") !== false;
    }

    private function insertChannelConfiguration($configContent): string
    {
        $channelConfig = <<<'EOD'
        
        'whatsapp' => [
            'driver' => 'daily',
            'path' => storage_path('logs/whatsapp.log'),
            'level' => 'debug',
            'days' => 7,
            'tap' => [\ScriptDevelop\WhatsappManager\Logging\CustomizeFormatter::class],
        ],
        EOD;

        return preg_replace(
            "/(\'channels\' => \[)/",
            "$1\n{$channelConfig}",
            $configContent
        );
    }
}