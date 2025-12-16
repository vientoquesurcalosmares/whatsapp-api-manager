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
            
            \Log::warning('WebhookProcessorInterface no pudo ser resuelto, usando implementación por defecto', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function handle(Request $request)
    {
        try {
            return $this->processor->handle($request);
        } catch (\Exception $e) {
            \Log::error('Error en el procesamiento del webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}