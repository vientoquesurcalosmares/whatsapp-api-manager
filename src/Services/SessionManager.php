<?php
namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\ChatSession;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Flow;
use ScriptDevelop\WhatsappManager\Models\FlowStep;
use ScriptDevelop\WhatsappManager\Models\WhatsappBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionManager {
    /**
     * Obtiene o crea una sesión activa para el contacto y bot
     * con manejo robusto de errores
     */
    public function getOrCreateSession(
        Contact $contact,
        WhatsappBot $bot,
        ?string $flowId = null
    ): ChatSession {
        return DB::transaction(function () use ($contact, $bot, $flowId) {
            try {
                // 1. Buscar sesión activa existente
                $session = $this->findActiveSession($contact, $bot);
                
                if ($session) {
                    return $session;
                }

                // 2. Validar y obtener flujo
                $flow = $this->validateFlow($bot, $flowId);
                
                // 3. Validar paso inicial del flujo
                $this->validateInitialStep($flow);

                // 4. Crear nueva sesión
                return ChatSession::create([
                    'contact_id' => $contact->contact_id,
                    'whatsapp_phone_id' => $bot->phone_number_id,
                    'assigned_bot_id' => $bot->whatsapp_bot_id,
                    'flow_id' => $flow->flow_id,
                    'current_step_id' => $flow->initialStep->step_id,
                    'status' => 'active',
                    'flow_status' => 'started',
                    'context' => []
                ]);

            } catch (\Exception $e) {
                Log::channel('whatsapp')->error("Error creando sesión: " . $e->getMessage(), [
                    'contact' => $contact->contact_id,
                    'bot' => $bot->whatsapp_bot_id
                ]);
                throw $e; // Relanzar para manejo en capa superior
            }
        });
    }

    /**
     * Cierra una sesión actualizando su estado
     */
    public function closeSession(ChatSession $session): void {
        $session->update([
            'flow_status' => 'completed',
            'status' => 'closed',
            'closed_at' => now()
        ]);
    }

    /**
     * Busca sesiones activas existentes
     */
    private function findActiveSession(Contact $contact, WhatsappBot $bot): ?ChatSession {
        return ChatSession::where('contact_id', $contact->contact_id)
            ->where('assigned_bot_id', $bot->whatsapp_bot_id)
            ->where('status', 'active')
            ->whereNull('assigned_agent_id')
            ->whereIn('flow_status', ['started', 'in_progress'])
            ->latest()
            ->first();
    }

    /**
     * Valida y obtiene el flujo a usar
     */
    private function validateFlow(WhatsappBot $bot, ?string $flowId): Flow
    {
        $flowId = $flowId ?? $bot->default_flow_id;

        if (!$flowId) {
            throw new \RuntimeException("Bot no tiene flujo por defecto configurado");
        }

        $flow = Flow::with('initialStep')->find($flowId); // Usar find() en lugar de findOrFail()

        if (!$flow) {
            throw new \RuntimeException("Flujo $flowId no existe en la base de datos");
        }

        if (!$flow->bots()->where('bot_flow.whatsapp_bot_id', $bot->whatsapp_bot_id)->exists()) {
            throw new \RuntimeException("Flujo no asociado al bot");
        }

        return $flow;
    }

    /**
     * Valida que el flujo tenga paso inicial
     */
    private function validateInitialStep(Flow $flow): void {
        if (!$flow->initialStep) {
            throw new \RuntimeException("Flow {$flow->flow_id} has no initial step");
        }
    }
}