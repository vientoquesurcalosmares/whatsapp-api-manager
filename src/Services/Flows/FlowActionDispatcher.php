<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows;

use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Contracts\FlowActionHandlerInterface;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowAction;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowSession;

class FlowActionDispatcher
{
    /**
     * Ejecuta todas las acciones habilitadas del flow con el trigger dado.
     * Captura excepciones individualmente — una acción fallida NO bloquea las siguientes.
     * No lanza excepciones — este método es fire-and-forget.
     */
    public function dispatch(
        WhatsappFlowSession $session,
        string $trigger = 'on_complete',
        array  $context = []
    ): void {
        if (!config('whatsapp.flows.collect_responses', true)) {
            return;
        }

        // Sesión orgánica sin flow → no hay acciones configuradas
        if (!$session->flow_id) {
            return;
        }

        try {
            $actionModel = config('whatsapp.models.flow_action', WhatsappFlowAction::class);

            $actions = $actionModel::where('flow_id', $session->flow_id)
                ->where('is_enabled', true)
                ->where('trigger', $trigger)
                ->orderBy('execution_order')
                ->get();

            foreach ($actions as $action) {
                try {
                    $handler = $this->getHandler($action);
                    $handler->execute($action, $session, $context);
                } catch (\Throwable $e) {
                    Log::channel('whatsapp')->error(
                        "FlowActionDispatcher: acción [{$action->name}] falló — {$e->getMessage()}",
                        [
                            'action_id'   => $action->flow_action_id,
                            'action_type' => $action->action_type,
                            'flow_id'     => $session->flow_id,
                        ]
                    );
                    // No re-throw: el resto de acciones sigue ejecutándose
                }
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error(
                'FlowActionDispatcher::dispatch error: ' . $e->getMessage(),
                ['session_id' => $session->flow_session_id, 'trigger' => $trigger]
            );
        }
    }

    /**
     * Instancia el handler correcto según action_type.
     * Los handlers se registran en config('whatsapp.flows.action_handlers').
     *
     * @throws \RuntimeException si no hay handler registrado para el tipo
     * @throws \InvalidArgumentException si la clase no implementa FlowActionHandlerInterface
     */
    public function getHandler(WhatsappFlowAction $action): FlowActionHandlerInterface
    {
        $handlers = config('whatsapp.flows.action_handlers', []);
        $class    = $handlers[$action->action_type] ?? null;

        if (!$class || !class_exists($class)) {
            throw new \RuntimeException(
                "FlowActionDispatcher: handler no registrado para action_type [{$action->action_type}]. "
                . "Registra la clase en config('whatsapp.flows.action_handlers')."
            );
        }

        $handler = app($class);

        if (!($handler instanceof FlowActionHandlerInterface)) {
            throw new \InvalidArgumentException(
                "{$class} debe implementar FlowActionHandlerInterface."
            );
        }

        return $handler;
    }
}
