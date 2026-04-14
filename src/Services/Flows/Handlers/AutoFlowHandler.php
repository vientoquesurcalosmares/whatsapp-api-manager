<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows\Handlers;

use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Contracts\FlowEndpointHandlerInterface;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowEndpointConfig;
use ScriptDevelop\WhatsappManager\Services\Flows\FlowResponse;
use ScriptDevelop\WhatsappManager\Services\Flows\FlowSessionService;

class AutoFlowHandler implements FlowEndpointHandlerInterface
{
    public function __construct(
        protected FlowSessionService $sessionService
    ) {}

    /**
     * Handle a Data API request in automatic (no-code) mode.
     *
     * Uses auto_config['screen_transitions'] to route between screens.
     * If no transition is found for the current screen, closes the flow.
     */
    public function handle(array $decryptedBody, ?WhatsappFlowEndpointConfig $config): array
    {
        $action     = $decryptedBody['action']     ?? 'data_exchange';
        $screen     = $decryptedBody['screen']     ?? null;
        $data       = $decryptedBody['data']       ?? [];
        $flowToken  = $decryptedBody['flow_token'] ?? null;
        $autoConfig = $config?->auto_config        ?? [];

        // INIT — primera carga del flow, retornar datos iniciales + primera pantalla
        if ($action === 'INIT') {
            $firstScreen = $autoConfig['first_screen'] ?? null;

            // Fallback: intentar leer del json_structure del flow
            if (!$firstScreen && $config?->flow) {
                $flow    = $config->flow;
                $screens = $flow->json_structure['screens'] ?? [];
                $firstScreen = $screens[0]['id'] ?? null;
            }

            if (!$firstScreen) {
                Log::channel('whatsapp')->warning('AutoFlowHandler: no se pudo determinar la primera pantalla. Usando PANTALLA_1 como fallback.');
                $firstScreen = 'PANTALLA_1';
            }

            $initData = $autoConfig['init_data'] ?? [];

            return FlowResponse::nextScreen($firstScreen, $initData);
        }

        // data_exchange — usuario avanzó de pantalla
        if ($action === 'data_exchange' && $screen) {
            // Acumular datos de la pantalla en la sesión
            if ($flowToken) {
                try {
                    $sessionModel = config('whatsapp.models.flow_session');
                    $session = $sessionModel::where('flow_token', $flowToken)->first();
                    if ($session) {
                        $this->sessionService->mergeScreenData($session, $screen, $data);
                    }
                } catch (\Throwable $e) {
                    Log::channel('whatsapp')->error(
                        'AutoFlowHandler: error guardando datos intermedios: ' . $e->getMessage()
                    );
                }
            }

            // Determinar siguiente pantalla desde screen_transitions
            $screenTransitions = $autoConfig['screen_transitions'] ?? [];
            $nextScreen        = $screenTransitions[$screen] ?? null;

            if ($nextScreen) {
                return FlowResponse::nextScreen($nextScreen, []);
            }

            // Sin siguiente pantalla → cerrar flow
            return FlowResponse::complete([]);
        }

        // BACK — Meta ignora la respuesta y muestra la pantalla anterior
        if ($action === 'BACK') {
            return [];
        }

        // ping — health check
        if ($action === 'ping') {
            return $this->ping();
        }

        return $this->ping();
    }

    /**
     * Respond to a health-check ping from Meta.
     */
    public function ping(): array
    {
        return FlowResponse::pong();
    }
}
