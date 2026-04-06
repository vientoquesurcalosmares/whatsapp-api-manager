<?php

namespace ScriptDevelop\WhatsappManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowSession;

class FlowSessionCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WhatsappFlowSession $session,
        public readonly array $finalData = []
    ) {}
}
