<?php

namespace ScriptDevelop\WhatsappManager\Contracts;

use ScriptDevelop\WhatsappManager\Models\WhatsappFlowEndpointConfig;

interface FlowEndpointHandlerInterface
{
    /**
     * Handle a Data API request from Meta.
     *
     * Receives the already-decrypted payload from FlowCryptoService.
     * The action field indicates the type of request:
     *   - 'INIT'          → first load, return initial screen data
     *   - 'data_exchange' → user moved to next screen, return next screen or complete
     *   - 'BACK'          → user pressed back, Meta ignores response (return empty array)
     *   - 'ping'          → health check, delegate to ping()
     *
     * @param  array                           $decryptedBody  Decrypted Meta payload
     * @param  WhatsappFlowEndpointConfig|null $config         Endpoint config or null if mode=auto without config
     * @return array                           Valid FlowResponse array for Meta (nextScreen, complete, error, or pong)
     */
    public function handle(array $decryptedBody, ?WhatsappFlowEndpointConfig $config): array;

    /**
     * Respond to a health-check ping from Meta.
     *
     * Meta sends periodic pings to verify the endpoint is alive.
     * Must respond within 8 seconds.
     *
     * @return array ['data' => ['status' => 'active']]
     */
    public function ping(): array;
}
