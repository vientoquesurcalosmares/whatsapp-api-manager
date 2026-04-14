<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows;

use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Contracts\FlowEndpointHandlerInterface;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowEndpointConfig;
use ScriptDevelop\WhatsappManager\Services\Flows\Handlers\AutoFlowHandler;
use ScriptDevelop\WhatsappManager\Services\Flows\Handlers\WebhookProxyHandler;

class FlowEndpointRouter
{
    public function __construct(
        protected FlowSessionService $sessionService
    ) {}

    /**
     * Enruta el data exchange al handler correcto.
     *
     * 1. Ping → respuesta directa (sin buscar config)
     * 2. Busca sesión por flow_token → flow → EndpointConfig
     * 3. Sin config o deshabilitado → respuesta genérica (backward compat)
     * 4. Delega al handler según config->mode
     */
    public function route(array $decryptedBody): array
    {
        // Ping siempre se responde directamente sin buscar config
        if (($decryptedBody['action'] ?? '') === 'ping') {
            return FlowResponse::pong();
        }

        $flowToken   = $decryptedBody['flow_token'] ?? null;
        $configModel = config(
            'whatsapp.models.flow_endpoint_config',
            WhatsappFlowEndpointConfig::class
        );

        // Sin flow_token → respuesta genérica
        if (!$flowToken) {
            return $this->genericResponse();
        }

        // Buscar sesión por flow_token → obtener flow_id
        $sessionModel = config('whatsapp.models.flow_session');
        $session      = $sessionModel::where('flow_token', $flowToken)->first();

        $config = null;
        if ($session?->flow_id) {
            $config = $configModel::where('flow_id', $session->flow_id)
                ->where('is_enabled', true)
                ->first();
        }

        // Sin config o deshabilitado → respuesta genérica (backward compat)
        if (!$config) {
            return $this->genericResponse();
        }

        // Delegar al handler según mode
        $handler = $this->resolveHandler($config);

        return $handler->handle($decryptedBody, $config);
    }

    /**
     * Factory de handlers. Valida que la clase implemente FlowEndpointHandlerInterface.
     */
    public function resolveHandler(WhatsappFlowEndpointConfig $config): FlowEndpointHandlerInterface
    {
        $handlerClass = match ($config->mode) {
            'auto'    => AutoFlowHandler::class,
            'webhook' => WebhookProxyHandler::class,
            'class'   => $config->handler_class,
            default   => AutoFlowHandler::class,
        };

        if (!$handlerClass || !class_exists($handlerClass)) {
            Log::channel('whatsapp')->error(
                "FlowEndpointRouter: handler class no encontrada [{$handlerClass}]. Usando AutoFlowHandler."
            );
            return app(AutoFlowHandler::class);
        }

        $handler = app($handlerClass);

        if (!($handler instanceof FlowEndpointHandlerInterface)) {
            Log::channel('whatsapp')->error(
                "FlowEndpointRouter: {$handlerClass} no implementa FlowEndpointHandlerInterface. Usando AutoFlowHandler."
            );
            return app(AutoFlowHandler::class);
        }

        return $handler;
    }

    /**
     * Busca un WhatsappFlow por wa_flow_id. Retorna null si no existe.
     */
    public function resolveFlow(string $waFlowId): ?\Illuminate\Database\Eloquent\Model
    {
        try {
            $flowModel = config('whatsapp.models.flow');
            return $flowModel::where('wa_flow_id', $waFlowId)->first();
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error(
                "FlowEndpointRouter::resolveFlow error: " . $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Respuesta genérica para cuando no hay config o el flow_token no está registrado.
     * Mantiene backward compatibility con el stub original.
     */
    protected function genericResponse(): array
    {
        return [
            'version' => config('whatsapp.flows.data_api_version', '3.0'),
            'data'    => ['status' => 'received'],
        ];
    }
}
