<?php
namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\ChatSession;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Flow;
use ScriptDevelop\WhatsappManager\Models\WhatsappBot;
use Illuminate\Support\Facades\DB;

class SessionManager {
    /**
     * Manejo de sesiones con reintentos
     */
    public function getOrCreateSession(
        Contact $contact,
        WhatsappBot $bot,
        ?string $flowId = null
    ): ChatSession {
        return DB::transaction(function () use ($contact, $bot, $flowId) {
            $session = ChatSession::where('contact_id', $contact->contact_id)
                ->where('assigned_bot_id', $bot->whatsapp_bot_id)
                ->where('status', 'active')
                ->whereNull('assigned_agent_id')
                ->whereIn('flow_status', ['started', 'in_progress'])
                ->latest() // Tomar la más reciente
                ->first();

            if (!$session) {
                $flowId = $flowId ?? $bot->default_flow_id;
                $flow = Flow::findOrFail($flowId);
                
                if (!$flow->initialStep) {
                    throw new \RuntimeException("Flow has no initial step");
                }

                $session = ChatSession::create([
                    'contact_id' => $contact->contact_id,
                    'whatsapp_phone_id' => $bot->phone_number_id,
                    'assigned_bot_id' => $bot->whatsapp_bot_id,
                    'flow_id' => $flowId,
                    'current_step_id' => $flow->initialStep->step_id,
                    'status' => 'active',
                    'flow_status' => 'started',
                    'context' => []
                ]);
            }

            return $session;
        });
    }

    public function closeSession(ChatSession $session): void {
        $session->update([
            'flow_status' => 'completed',
            'status' => 'closed'
        ]);
    }
}