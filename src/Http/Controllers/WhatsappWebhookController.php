<?php

namespace ScriptDevelop\WhatsappManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ScriptDevelop\WhatsappManager\Contracts\WebhookProcessorInterface;
use ScriptDevelop\WhatsappManager\Services\WebhookProcessors\BaseWebhookProcessor;

class WhatsappWebhookController extends Controller
{
    protected $processor;
    
    public function __construct()
    {
        try {
            // Intentar resolver desde el contenedor de servicios
            $this->processor = app(WebhookProcessorInterface::class);
        } catch (\Exception $e) {
            // Fallback a la implementación por defecto
            $this->processor = new BaseWebhookProcessor();

            \Log::warning(whatsapp_trans('messages.webhook_processor_not_resolved'), [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function handle(Request $request)
    {
        try {
            return $this->processor->handle($request);
        } catch (\Exception $e) {
            \Log::error(whatsapp_trans('messages.webhook_processing_error'), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}