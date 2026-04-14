<?php

namespace ScriptDevelop\WhatsappManager\Services\Flows\Handlers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Contracts\FlowEndpointHandlerInterface;
use ScriptDevelop\WhatsappManager\Models\WhatsappFlowEndpointConfig;
use ScriptDevelop\WhatsappManager\Services\Flows\FlowResponse;

class WebhookProxyHandler implements FlowEndpointHandlerInterface
{
    /**
     * Proxy the decrypted flow body to an external webhook URL.
     *
     * Signs the request with HMAC-SHA256 if webhook_secret is configured.
     * The external endpoint is responsible for returning a valid FlowResponse JSON.
     */
    public function handle(array $decryptedBody, ?WhatsappFlowEndpointConfig $config): array
    {
        if (!$config?->webhook_url) {
            Log::channel('whatsapp')->error('WebhookProxyHandler: webhook_url no configurada.');
            return FlowResponse::error('configuration_error');
        }

        $timeoutMs  = $config->webhook_timeout_ms
            ?? config('whatsapp.flows.endpoint_timeout', 6000);
        $timeoutSec = max(1, (int) ($timeoutMs / 1000));

        $body    = json_encode($decryptedBody);
        $headers = ['Content-Type' => 'application/json'];

        // Firma HMAC si hay secret configurado
        if ($config->webhook_secret) {
            $signature = hash_hmac('sha256', $body, $config->webhook_secret);
            $headers['X-Flow-Signature'] = "sha256={$signature}";
        }

        // Header informativo con la acción
        $headers['X-Flow-Action'] = $decryptedBody['action'] ?? '';

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeoutSec)
                ->post($config->webhook_url, $decryptedBody);

            if ($response->successful()) {
                $decoded = $response->json();

                if (!is_array($decoded)) {
                    Log::channel('whatsapp')->warning('WebhookProxyHandler: respuesta no es JSON válido.', [
                        'url'  => $config->webhook_url,
                        'body' => $response->body(),
                    ]);
                    return FlowResponse::error('invalid_response');
                }

                // Si el webhook retornó estructura FlowResponse válida (tiene 'version'), pasarla directo
                if (isset($decoded['version'])) {
                    return $decoded;
                }

                // Si retornó JSON sin estructura de Flow → envolver
                return [
                    'version' => config('whatsapp.flows.data_api_version', '3.0'),
                    'data'    => $decoded,
                ];
            }

            Log::channel('whatsapp')->error('WebhookProxyHandler: upstream devolvió error.', [
                'status' => $response->status(),
                'url'    => $config->webhook_url,
            ]);

            return [
                'version' => config('whatsapp.flows.data_api_version', '3.0'),
                'data'    => ['error' => "upstream_{$response->status()}"],
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::channel('whatsapp')->error('WebhookProxyHandler: timeout/error de conexión.', [
                'url'       => $config->webhook_url,
                'timeout_s' => $timeoutSec,
                'error'     => $e->getMessage(),
            ]);
            return [
                'version' => config('whatsapp.flows.data_api_version', '3.0'),
                'data'    => ['error' => 'endpoint_unavailable'],
            ];
        }
    }

    /**
     * Respond to a health-check ping from Meta.
     */
    public function ping(): array
    {
        return FlowResponse::pong();
    }
}
