<?php
namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\ChatSession;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Flow;
use ScriptDevelop\WhatsappManager\Models\WhatsappBot;

class SessionManager {
    public function getOrCreateSession(
        Contact $contact,
        WhatsappBot $bot,
        ?string $flowId = null
    ): ChatSession {
        // 1. Buscar sesión activa no asignada a agente
        $session = ChatSession::where('contact_id', $contact->contact_id)
            ->where('assigned_bot_id', $bot->whatsapp_bot_id)
            ->whereNull('assigned_agent_id')
            ->where('flow_status', '!=', 'completed')
            ->first();

        // 2. Crear nueva sesión si no existe
        if (!$session) {
            // Obtener el paso inicial del flujo
            $flow = Flow::find($flowId);
            $initialStep = Flow::find($flowId)?->initialStep;

            $session = ChatSession::create([
                'contact_id' => $contact->contact_id,
                'whatsapp_phone_id' => $bot->phone_number_id,
                'assigned_bot_id' => $bot->whatsapp_bot_id,
                'flow_id' => $flowId ?? $bot->default_flow_id,
                'current_step_id' => $flow?->initialStep?->step_id,
                'status' => 'active',
                'flow_status' => 'started',
                'context' => []
            ]);
        }

        return $session;
    }

    public function closeSession(ChatSession $session): void {
        $session->update([
            'flow_status' => 'completed',
            'status' => 'closed'
        ]);
    }
}