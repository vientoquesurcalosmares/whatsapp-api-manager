<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Events\FlowSessionCompleted;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowSession;

class FlowSessionService
{
    /**
     * Busca sesión por flow_token o la crea.
     * Actualiza phone_number_id/contact_id si la sesión existente los tiene null.
     *
     * Para flows orgánicos (token generado por Meta, no por nosotros), is_organic=true.
     * Si no se puede resolver el flow_id, la sesión se crea con flow_id = null.
     */
    public function findOrCreateSession(
        string  $flowToken,
        ?string $waFlowId,
        ?Model  $phoneNumber,
        ?Model  $contact,
        string  $sendMethod = 'organic',
        bool    $isOrganic  = true,
        ?Model  $sentByUser = null
    ): WhatsappFlowSession {
        try {
            $sessionModel = config('whatsapp.models.flow_session', WhatsappFlowSession::class);

            // 1. Buscar sesión existente por token único
            $session = $sessionModel::where('flow_token', $flowToken)->first();

            if ($session) {
                // Enriquecer datos si antes eran nulos
                $updates = [];
                if (!$session->phone_number_id && $phoneNumber) {
                    $updates['phone_number_id'] = $phoneNumber->getKey();
                }
                if (!$session->contact_id && $contact) {
                    $updates['contact_id'] = $contact->getKey();
                }
                if (!empty($updates)) {
                    $session->update($updates);
                }
                return $session;
            }

            // 2. Resolver flow_id local por wa_flow_id
            $flowId = null;
            if ($waFlowId) {
                $flowModel = config('whatsapp.models.flow');
                $localFlow = $flowModel::where('wa_flow_id', $waFlowId)->first();
                $flowId    = $localFlow?->flow_id;
            }

            // 3. Heurística para flujos orgánicos sin wa_flow_id explícito
            if (!$flowId && $phoneNumber && $isOrganic) {
                $flowId = $this->resolveFlowByPhoneNumber($phoneNumber);
            }

            // 4. Crear sesión
            return $sessionModel::create([
                'flow_id'         => $flowId,
                'user_phone'      => $phoneNumber
                    ? ($phoneNumber->display_phone_number ?? 'unknown')
                    : 'unknown',
                'flow_token'      => $flowToken,
                'status'          => 'active',
                'is_organic'      => $isOrganic,
                'send_method'     => $sendMethod,
                'phone_number_id' => $phoneNumber ? $phoneNumber->getKey() : null,
                'contact_id'      => $contact ? $contact->getKey() : null,
                'sent_by_user_id' => $sentByUser ? $sentByUser->getKey() : null,
            ]);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error(
                'FlowSessionService::findOrCreateSession error: ' . $e->getMessage(),
                ['flow_token' => $flowToken]
            );
            throw $e;
        }
    }

    /**
     * Crear sesión proactivamente cuando nosotros enviamos el flow.
     * Se llama ANTES de enviar el template/mensaje.
     * is_organic = false.
     */
    public function createProactive(
        Model  $flow,
        Model  $phoneNumber,
        ?Model $contact,
        string $sendMethod,   // 'template' | 'interactive'
        string $flowToken,
        ?Model $sentByUser = null
    ): WhatsappFlowSession {
        $sessionModel = config('whatsapp.models.flow_session', WhatsappFlowSession::class);

        return $sessionModel::create([
            'flow_id'         => $flow->flow_id,
            'user_phone'      => $phoneNumber->display_phone_number ?? 'unknown',
            'flow_token'      => $flowToken,
            'status'          => 'active',
            'is_organic'      => false,
            'send_method'     => $sendMethod,
            'phone_number_id' => $phoneNumber->getKey(),
            'contact_id'      => $contact ? $contact->getKey() : null,
            'sent_by_user_id' => $sentByUser ? $sentByUser->getKey() : null,
        ]);
    }

    /**
     * Crear o encontrar sesión reactivamente cuando llega el webhook.
     * Alias conveniente de findOrCreateSession para uso en handlers/processor.
     */
    public function createOrFindReactive(
        string  $flowToken,
        ?string $waFlowId,
        ?Model  $phoneNumber,
        ?Model  $contact
    ): WhatsappFlowSession {
        return $this->findOrCreateSession(
            flowToken:   $flowToken,
            waFlowId:    $waFlowId,
            phoneNumber: $phoneNumber,
            contact:     $contact,
            sendMethod:  'organic',
            isOrganic:   true
        );
    }

    /**
     * Marca la sesión como completada.
     * Hace merge de intermediate_data + finalData en collected_data.
     */
    public function completeSession(WhatsappFlowSession $session, array $finalData = []): void
    {
        try {
            $existing     = $session->collected_data ?? [];
            $intermediate = $session->intermediate_data ?? [];
            $merged       = array_merge($intermediate, $existing, $finalData);

            $session->update([
                'status'         => 'completed',
                'completed_at'   => now(),
                'collected_data' => $merged,
            ]);

            Event::dispatch(new FlowSessionCompleted($session, $finalData));
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error(
                'FlowSessionService::completeSession error: ' . $e->getMessage(),
                ['session_id' => $session->flow_session_id]
            );
        }
    }

    /**
     * Alias para completeSession — compatibilidad con TASK-19 naming.
     */
    public function markCompleted(WhatsappFlowSession $session, array $finalData = []): void
    {
        $this->completeSession($session, $finalData);
    }

    /**
     * Marca la sesión como abandonada.
     */
    public function abandonSession(WhatsappFlowSession $session): void
    {
        try {
            $session->update([
                'status'       => 'failed',
                'abandoned_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error(
                'FlowSessionService::abandonSession error: ' . $e->getMessage(),
                ['session_id' => $session->flow_session_id]
            );
        }
    }

    /**
     * Alias para abandonSession.
     */
    public function markAbandoned(WhatsappFlowSession $session): void
    {
        $this->abandonSession($session);
    }

    /**
     * Acumula datos de una pantalla intermedia (Data API).
     * Actualiza intermediate_data[screenName] = screenData y current_screen.
     */
    public function mergeScreenData(
        WhatsappFlowSession $session,
        string $screenName,
        array  $screenData
    ): void {
        try {
            $current = $session->intermediate_data ?? [];
            $current[$screenName] = $screenData;

            $session->update([
                'intermediate_data' => $current,
                'current_screen'    => $screenName,
            ]);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error(
                'FlowSessionService::mergeScreenData error: ' . $e->getMessage(),
                ['session_id' => $session->flow_session_id, 'screen' => $screenName]
            );
        }
    }

    /**
     * Alias para mergeScreenData — compatibilidad con el task.
     */
    public function updateIntermediateData(
        WhatsappFlowSession $session,
        string $screen,
        array  $data
    ): void {
        $this->mergeScreenData($session, $screen, $data);
    }

    /**
     * Heurística: retorna el flow_id del flow más reciente PUBLISHED del número.
     * Acepta la limitación de ambigüedad si hay múltiples flows publicados.
     */
    protected function resolveFlowByPhoneNumber(Model $phoneNumber): ?string
    {
        try {
            $accountId = $phoneNumber->whatsapp_business_account_id ?? null;
            if (!$accountId) {
                return null;
            }

            $flowModel = config('whatsapp.models.flow');
            $flow = $flowModel::where('whatsapp_business_account_id', $accountId)
                ->whereIn('status', ['published', 'PUBLISHED'])
                ->orderBy('updated_at', 'desc')
                ->first();

            return $flow?->flow_id;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning(
                'FlowSessionService::resolveFlowByPhoneNumber error: ' . $e->getMessage()
            );
            return null;
        }
    }
}
