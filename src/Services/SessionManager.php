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
    public function getOrCreateSession(
        Contact $contact,
        WhatsappBot $bot,
        ?string $flowId = null
    ): ChatSession {
        return DB::transaction(function () use ($contact, $bot, $flowId) {
            $session = $this->findActiveSession($contact, $bot);
            
            if ($session) {
                return $session->load(['currentStep', 'flow.entryPoint']);
            }

            $flow = $this->validateFlow($bot, $flowId);
            $this->validateEntryPoint($flow);

            $session = ChatSession::create([
                'contact_id' => $contact->contact_id,
                'whatsapp_phone_id' => $bot->phone_number_id,
                'assigned_bot_id' => $bot->whatsapp_bot_id,
                'flow_id' => $flow->flow_id,
                'current_step_id' => $flow->entry_point_id,
                'status' => 'active',
                'flow_status' => 'started',
                'context' => []
            ]);

            // Cargar relaciones críticas inmediatamente
            return $session->load([
                'currentStep',
                'flow.entryPoint',
                'bot.phoneNumber'
            ]);
        });
    }

    private function findActiveSession(Contact $contact, WhatsappBot $bot): ?ChatSession {
        return ChatSession::where('contact_id', $contact->contact_id)
            ->where('assigned_bot_id', $bot->whatsapp_bot_id)
            ->where('status', 'active')
            ->where('flow_status', '!=', 'completed')
            ->latest()
            ->first();  
    }

    private function validateFlow(WhatsappBot $bot, ?string $flowId): Flow {
        $flowId = $flowId ?? $bot->default_flow_id;

        if (!$flowId) {
            throw new \RuntimeException("Bot no tiene flujo por defecto configurado");
        }

        // Cargar relación entryPoint anticipadamente
        $flow = Flow::with('entryPoint')->find($flowId);

        if (!$flow) {
            throw new \RuntimeException("Flujo $flowId no existe");
        }

        if (!$flow->bots()->where('bot_flow.whatsapp_bot_id', $bot->whatsapp_bot_id)->exists()) {
            throw new \RuntimeException("Flujo no asociado al bot");
        }

        return $flow;
    }

    private function validateEntryPoint(Flow $flow): void {
        if (!$flow->entryPoint) {
            throw new \RuntimeException("Flujo sin punto de entrada definido");
        }
    }
}