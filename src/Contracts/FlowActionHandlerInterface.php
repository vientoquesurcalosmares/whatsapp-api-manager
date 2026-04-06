<?php

namespace ScriptDevelop\WhatsappManager\Contracts;

use ScriptDevelop\WhatsappManager\Models\WhatsappFlowAction;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowSession;

interface FlowActionHandlerInterface
{
    /**
     * Execute a configured action triggered by a flow event.
     *
     * The FlowActionDispatcher calls this method for each enabled action
     * matching the trigger. Exceptions are caught by the dispatcher and logged
     * individually — a failing action does NOT block subsequent actions.
     *
     * @param  WhatsappFlowAction  $action   The action with its config and type
     * @param  WhatsappFlowSession $session  The flow session that triggered the action
     * @param  array               $context  Additional context data (e.g., decoded nfm_reply)
     * @throws \Exception  If execution fails (dispatcher catches and logs, does not re-throw)
     */
    public function execute(
        WhatsappFlowAction  $action,
        WhatsappFlowSession $session,
        array               $context = []
    ): void;

    /**
     * Indicate whether this exception should trigger a retry.
     *
     * Called by the dispatcher when retry_config is present on the action.
     * Return true for transient failures (network, timeout) that are worth retrying.
     * Return false for permanent failures (invalid config, auth errors) where
     * retrying would waste resources.
     *
     * @param  \Throwable $e  The exception that was thrown during execute()
     * @return bool
     */
    public function shouldRetry(\Throwable $e): bool;
}
