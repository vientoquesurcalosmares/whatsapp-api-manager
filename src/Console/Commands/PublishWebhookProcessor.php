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
        $destination = app_path('Services/WhatsappWebhookProcessor.php');

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

        // Copy the file
        File::copy($source, $destination);

        // Update the namespace
        $content = File::get($destination);
        $content = str_replace(
            'namespace ScriptDevelop\WhatsappManager\Services\WebhookProcessors;',
            'namespace App\Services;',
            $content
        );
        File::put($destination, $content);

        $this->info('Webhook processor published successfully: ' . $destination);

        // Check if config needs updating
        $configPath = config_path('whatsapp.php');
        if (File::exists($configPath)) {
            $configContent = File::get($configPath);
            if (strpos($configContent, "'processor'") === false) {
                $newConfigContent = str_replace(
                    "'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),",
                    "'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),\n\n    // Procesador personalizado para webhooks\n    'processor' => \App\Services\WhatsappWebhookProcessor::class,",
                    $configContent
                );
                File::put($configPath, $newConfigContent);
                $this->info('Configuration updated to use custom processor.');
            } else {
                $this->info('Note: You may need to update your whatsapp config to use the custom processor.');
            }
        } else {
            $this->info('Note: You need to update your whatsapp config to use the custom processor:');
            $this->info("'processor' => \App\Services\WhatsappWebhookProcessor::class");
        }
    }
}