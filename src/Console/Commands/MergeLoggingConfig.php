<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MergeLoggingConfig extends Command
{
    protected $signature = 'whatsapp:merge-logging';
    protected $description;

    public function __construct()
    {
        parent::__construct();
        $this->description = whatsapp_trans('console.merge_logging_description');
    }

    public function handle()
    {
        $projectConfigPath = config_path('logging.php');
        $channelConfig = $this->getChannelConfig();

        try {
            if (!File::exists($projectConfigPath)) {
                $this->error(whatsapp_trans('console.logging_file_not_found', ['path' => $projectConfigPath]));
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
                    $this->error(whatsapp_trans('console.error_modifying_config', ['error' => 'regex pattern did not match']));
                    return 2;
                }

                File::put($projectConfigPath, $newContent);
                $this->info(whatsapp_trans('console.channel_added_success', ['path' => $projectConfigPath]));
                return 0;
            }

            $this->info(whatsapp_trans('console.channel_already_exists', ['path' => $projectConfigPath]));
            return 0;

        } catch (\Exception $e) {
            $this->error(whatsapp_trans('console.critical_error', ['error' => $e->getMessage()]));
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