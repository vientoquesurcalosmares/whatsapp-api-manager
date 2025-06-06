<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\WhatsappFlow;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class FlowService
{
    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Sincroniza flujos desde la API de WhatsApp
     */
    public function syncFlows(WhatsappBusinessAccount $account): Collection
    {
        $endpoint = Endpoints::build(Endpoints::GET_FLOWS, [
            'waba_id' => $account->whatsapp_business_id,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        try {
            $response = $this->apiClient->request('GET', $endpoint, [], null, [], $headers);
            $flows = $response['data'] ?? [];

            foreach ($flows as $flowData) {
                $this->storeOrUpdateFlow($account->whatsapp_business_id, $flowData);
            }

            // Marcar flujos no encontrados como inactivos
            $apiFlowIds = collect($flows)->pluck('id')->toArray();
            WhatsappFlow::where('whatsapp_business_account_id', $account->whatsapp_business_id)
                ->whereNotIn('wa_flow_id', $apiFlowIds)
                ->update(['status' => 'INACTIVE']);

            return WhatsappFlow::where('whatsapp_business_account_id', $account->whatsapp_business_id)->get();

        } catch (\Exception $e) {
            Log::error('Error al sincronizar flujos: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'business_id' => $account->whatsapp_business_id
            ]);
            throw $e;
        }
    }

    /**
     * Crea o actualiza un flujo en la base de datos
     */
    protected function storeOrUpdateFlow(string $businessId, array $flowData): WhatsappFlow
    {
        return WhatsappFlow::updateOrCreate(
            ['wa_flow_id' => $flowData['id']],
            [
                'whatsapp_business_account_id' => $businessId,
                'name' => $flowData['name'],
                'flow_type' => $flowData['flow_type'] ?? 'UNKNOWN',
                'description' => $flowData['description'] ?? '',
                'json_structure' => $flowData['json_structure'] ?? null,
                'status' => $flowData['status'] ?? 'DRAFT',
                'version' => $flowData['version'] ?? '1.0',
            ]
        );

        // Procesar screens y elements si existen en json_structure
        if (!empty($flowData['json_structure']['screens'])) {
            $this->syncScreensAndElements($flow, $flowData['json_structure']['screens']);
        }

        return $flow;
    }

    /**
     * Obtiene un flujo por ID de API
     */
    public function getFlowById(string $flowId): ?WhatsappFlow
    {
        return WhatsappFlow::where('wa_flow_id', $flowId)->first();
    }

    /**
     * Crea un nuevo constructor de flujos
     */
    public function createFlowBuilder(WhatsappBusinessAccount $account): FlowBuilder
    {
        return new FlowBuilder($this->apiClient, $account, $this);
    }

    public function syncFlowById(WhatsappBusinessAccount $account, string $flowId): ?WhatsappFlow
    {
        $endpoint = Endpoints::build(Endpoints::GET_FLOW, [
            'flow_id' => $flowId,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $account->api_token,
        ];

        try {
            $response = $this->apiClient->request('GET', $endpoint, [], null, [], $headers);
            
            // Asegurar que la respuesta tiene los datos necesarios
            if (empty($response['id']) || empty($response['name'])) {
                Log::error('Respuesta de flujo inválida', ['response' => $response]);
                return null;
            }
            
            return $this->storeOrUpdateFlow($account->whatsapp_business_id, $response);
        } catch (\Exception $e) {
            Log::error('Error al sincronizar flujo por ID: ' . $e->getMessage(), [
                'flow_id' => $flowId,
            ]);
            return null;
        }
    }

    /**
     * Sincroniza las pantallas y elementos de un flujo.
     *
     * @param WhatsappFlow $flow
     * @param array $screens
     * @return void
     */
    protected function syncScreensAndElements(WhatsappFlow $flow, array $screens): void
    {
        foreach ($screens as $screenData) {
            // Guardar o actualizar la pantalla
            $screen = $flow->screens()->updateOrCreate(
                ['name' => $screenData['name']],
                [
                    'title' => $screenData['title'] ?? '',
                    'content' => $screenData['content'] ?? null,
                    'is_start' => $screenData['is_start'] ?? false,
                    'order' => $screenData['order'] ?? 0,
                    'validation_rules' => $screenData['validation_rules'] ?? null,
                    'next_screen_logic' => $screenData['next_screen_logic'] ?? null,
                    'extra_logic' => $screenData['extra_logic'] ?? null,
                ]
            );

            // Guardar elementos de la pantalla
            if (!empty($screenData['elements'])) {
                foreach ($screenData['elements'] as $elementData) {
                    $screen->elements()->updateOrCreate(
                        ['name' => $elementData['name']],
                        [
                            'type' => $elementData['type'] ?? '',
                            'label' => $elementData['label'] ?? '',
                            'placeholder' => $elementData['placeholder'] ?? null,
                            'default_value' => $elementData['default_value'] ?? null,
                            'options' => $elementData['options'] ?? null,
                            'style_json' => $elementData['style_json'] ?? null,
                            'required' => $elementData['required'] ?? false,
                            'validation' => $elementData['validation'] ?? null,
                            'next_screen' => $elementData['next_screen'] ?? null,
                        ]
                    );
                }
            }
        }
    }
}