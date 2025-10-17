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

        // Create template content instead of copying the full BaseWebhookProcessor
        $content = $this->getTemplateContent();

        File::put($destination, $content);

        $this->info('Webhook processor template published successfully: ' . $destination);
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Review the template and customize according to your needs');
        $this->info('2. Add your custom logic to the methods');
        $this->info('3. The configuration has been updated automatically');

        // Update configuration
        $this->updateConfiguration();
    }

    protected function getTemplateContent(): string
    {
        return <<<'PHP'
<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use ScriptDevelop\WhatsappManager\Services\WebhookProcessors\BaseWebhookProcessor;

/**
 * Custom WhatsApp Webhook Processor
 * 
 * Extend this class to add custom functionality to your WhatsApp webhook processing.
 * You can override any method from BaseWebhookProcessor to implement your own logic.
 * 
 * Common methods to override:
 * - handle() - Main webhook processing
 * - processTextMessage() - Text messages
 * - processMediaMessage() - Images, audio, video, documents
 * - processInteractiveMessage() - Interactive buttons and lists
 * - handleStatusUpdate() - Message status updates (delivered, read, failed)
 * - handleTemplateEvent() - Template-related events
 * 
 * See BaseWebhookProcessor for all available methods.
 */
class WhatsappWebhookProcessor extends BaseWebhookProcessor
{
    /**
     * URL for webhook redirection (optional)
     */
    protected $redirectUrl;

    public function __construct()
    {
        // Initialize any custom configuration here
        $this->redirectUrl = config('whatsapp.webhook.redirect_url', env('WHATSAPP_WEBHOOK_REDIRECT_URL'));
        
        // Call parent constructor if needed
        // parent::__construct();
    }

    /**
     * Main webhook handler - override to add custom pre/post processing
     */
    public function handle(Request $request): Response|JsonResponse
    {
        // Add custom pre-processing logic here
        $this->customPreProcessing($request);

        // Process webhook normally
        $response = parent::handle($request);

        // Add custom post-processing logic here
        $this->customPostProcessing($request);
        
        // Optional: Redirect webhook to external URL
        $this->redirectToExternalUrl($request);

        return $response;
    }

    /**
     * EXAMPLE: Custom text message processing
     * Uncomment and modify as needed
     */
    /*
    protected function processTextMessage(array $message, $contact, $whatsappPhone)
    {
        // Add your custom logic here
        $textContent = $message['text']['body'] ?? '';
        
        if (str_contains(strtolower($textContent), 'hello')) {
            \Log::info("Greeting received from: {$contact->phone_number}");
        }
        
        if (str_contains(strtolower($textContent), 'help')) {
            return $this->handleHelpCommand($message, $contact, $whatsappPhone);
        }

        // Call parent to maintain standard processing
        return parent::processTextMessage($message, $contact, $whatsappPhone);
    }
    */

    /**
     * EXAMPLE: Custom media message processing
     * Uncomment and modify as needed
     */
    /*
    protected function processMediaMessage(array $message, $contact, $whatsappPhone)
    {
        // Custom logic for media messages
        $mediaType = $message['type'];
        \Log::info("Media received - Type: {$mediaType}, From: {$contact->phone_number}");

        // Add custom media processing (AI analysis, storage, etc.)
        if ($mediaType === 'image') {
            $this->analyzeImage($message, $contact);
        }

        // Call parent to maintain standard processing
        return parent::processMediaMessage($message, $contact, $whatsappPhone);
    }
    */

    /**
     * EXAMPLE: Custom status update handling
     * Uncomment and modify as needed
     */
    /*
    protected function handleStatusUpdate(array $status): void
    {
        // Custom logic for status updates
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;
        
        \Log::info("Message status update - ID: {$messageId}, Status: {$statusValue}");

        // Add custom status tracking logic
        $this->trackMessageStatus($status);

        // Call parent to maintain standard processing
        parent::handleStatusUpdate($status);
    }
    */

    /**
     * EXAMPLE: Webhook redirection to external service
     */
    protected function redirectToExternalUrl(Request $request): void
    {
        if (empty($this->redirectUrl)) {
            return;
        }

        try {
            $payload = [
                'timestamp' => now()->toISOString(),
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ];

            Http::timeout(10)
                ->retry(3, 100)
                ->post($this->redirectUrl, $payload);

            \Log::channel('whatsapp')->info('Webhook forwarded to external URL', [
                'url' => $this->redirectUrl
            ]);

        } catch (\Exception $e) {
            \Log::channel('whatsapp')->error('Error forwarding webhook', [
                'url' => $this->redirectUrl,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * EXAMPLE: Custom pre-processing logic
     */
    protected function customPreProcessing(Request $request): void
    {
        // Add any pre-processing logic here
        // Example: Validate request, log specific data, etc.
        \Log::channel('whatsapp')->debug('Webhook received', [
            'method' => $request->method(),
            'has_payload' => !empty($request->all())
        ]);
    }

    /**
     * EXAMPLE: Custom post-processing logic
     */
    protected function customPostProcessing(Request $request): void
    {
        // Add any post-processing logic here
        // Example: Trigger external APIs, update databases, etc.
    }

    // =========================================================================
    // CUSTOM METHOD EXAMPLES - Add your own methods below
    // =========================================================================

    /**
     * EXAMPLE: Handle help command
     */
    /*
    protected function handleHelpCommand(array $message, $contact, $whatsappPhone)
    {
        // Implement help command logic
        \Log::info("Help command triggered by: {$contact->phone_number}");
        
        // You can choose to not save the message by returning null
        // or modify the message before saving
        return null;
    }
    */

    /**
     * EXAMPLE: Analyze image content
     */
    /*
    protected function analyzeImage(array $message, $contact): void
    {
        // Integrate with image analysis services
        // Example: Google Vision API, AWS Rekognition, etc.
        \Log::info("Image analysis for contact: {$contact->phone_number}");
    }
    */

    /**
     * EXAMPLE: Track message status in external system
     */
    /*
    protected function trackMessageStatus(array $status): void
    {
        // Integrate with external analytics or CRM
        \Log::info("Tracking message status in external system");
    }
    */
}
PHP;
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
                "'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),\n\n    // Custom webhook processor\n    'processor' => \\App\\Services\\Whatsapp\\WhatsappWebhookProcessor::class,",
                $configContent
            );
        }

        File::put($configPath, $newConfigContent);
        $this->info('Configuration updated to use custom processor.');
    }
}