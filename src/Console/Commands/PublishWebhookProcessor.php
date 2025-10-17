<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishWebhookProcessor extends Command
{
    protected $signature = 'whatsapp:publish-webhook-processor {--force : Overwrite existing file}';
    protected $description = 'Publish the webhook processor for customization';

    public function handle()
    {
        $source = __DIR__ . '/../../Services/WebhookProcessors/BaseWebhookProcessor.php';
        $destination = app_path('Services/Whatsapp/WhatsappWebhookProcessor.php');

        // Check if directory exists
        $dir = dirname($destination);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Check if file already exists
        if (File::exists($destination) && !$this->option('force')) {
            $this->error('Webhook processor already exists. Use --force to overwrite.');
            return;
        }

        // Read and modify the source content
        $content = File::get($source);
        
        // Update namespace and class name
        $content = str_replace(
            [
                'namespace ScriptDevelop\WhatsappManager\Services\WebhookProcessors;',
                'class BaseWebhookProcessor',
                'extends BaseWebhookProcessor'
            ],
            [
                'namespace App\Services\Whatsapp;',
                'class WhatsappWebhookProcessor',
                'extends \\ScriptDevelop\\WhatsappManager\\Services\\WebhookProcessors\\BaseWebhookProcessor'
            ],
            $content
        );

        // Add use statement for the base class
        $useStatement = "use ScriptDevelop\\WhatsappManager\\Services\\WebhookProcessors\\BaseWebhookProcessor;\n\n";
        $content = preg_replace('/^namespace App\\\\Services\\\\Whatsapp;/m', "namespace App\\Services\\Whatsapp;\n\n" . $useStatement, $content);

        File::put($destination, $content);

        $this->info('Webhook processor published successfully: ' . $destination);

        // Update configuration
        $this->updateConfiguration();
    }

    protected function updateConfiguration(): void
    {
        $configPath = config_path('whatsapp.php');
        
        if (!File::exists($configPath)) {
            $this->warn('Configuration file not found. Please update your whatsapp config manually:');
            $this->info("'processor' => \\App\\Services\\Whatsapp\\WhatsappWebhookProcessor::class");
            return;
        }

        $configContent = File::get($configPath);
        
        // Check if processor configuration already exists
        if (strpos($configContent, "'processor'") !== false) {
            // Update existing processor configuration
            $newConfigContent = preg_replace(
                "/'processor' => [^,]+,/",
                "'processor' => \\App\\Services\\Whatsapp\\WhatsappWebhookProcessor::class,",
                $configContent
            );
        } else {
            // Add processor configuration
            $newConfigContent = str_replace(
                "'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),",
                "'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),\n\n    // Procesador personalizado para webhooks\n    'processor' => \\App\\Services\\Whatsapp\\WhatsappWebhookProcessor::class,",
                $configContent
            );
        }

        File::put($configPath, $newConfigContent);
        $this->info('Configuration updated to use custom processor.');
    }
}