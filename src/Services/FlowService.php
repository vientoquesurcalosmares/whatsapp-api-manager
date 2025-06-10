<?php

namespace ScriptDevelop\WhatsappManager\Services;

use InvalidArgumentException;
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

    public function edit(WhatsappFlow $flow): FlowEditor
    {
        return new FlowEditor(
            $flow,
            $this->apiClient,
            $this
        );
    }

    public function editor(WhatsappFlow $flow): FlowEditor
    {
        return $this->edit($flow);
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
                // Obtener detalles completos del flujo
                $flowDetailEndpoint = Endpoints::build(Endpoints::GET_FLOW, [
                    'flow_id' => $flowData['id'],
                ]);
                $flowDetail = $this->apiClient->request(
                    'GET',
                    $flowDetailEndpoint,
                    [],
                    null,
                    [
                        'fields' => 'id,name,categories,preview,status,validation_errors,json_version,data_api_version,data_channel_uri,health_status,whatsapp_business_account,application'
                    ],
                    $headers
                );

                $this->storeOrUpdateFlow($account->whatsapp_business_id, $flowDetail);
            }

            // Marcar flujos no encontrados como inactivos
            $apiFlowIds = collect($flows)->pluck('id')->toArray();
            WhatsappFlow::where('whatsapp_business_account_id', $account->whatsapp_business_id)
                ->whereNotIn('wa_flow_id', $apiFlowIds)
                ->update(['status' => 'INACTIVE']);

            return WhatsappFlow::where('whatsapp_business_account_id', $account->whatsapp_business_id)->get();

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al sincronizar flujos: ' . $e->getMessage(), [
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
                // 'flow_type' => $flowData['flow_type'] ?? 'UNKNOWN',
                // 'description' => $flowData['description'] ?? '',
                // 'json_structure' => $flowData['json_structure'] ?? null,
                'status' => $flowData['status'] ?? 'DRAFT',
                'version' => $flowData['version'] ?? '1.0',

                'categories' => $flowData['categories'] ?? null,
                'preview_url' => $flowData['preview']['preview_url'] ?? null,
                'preview_expires_at' => $flowData['preview']['expires_at'] ?? null,
                'validation_errors' => $flowData['validation_errors'] ?? null,
                'json_version' => $flowData['json_version'] ?? null,
                'health_status' => $flowData['health_status'] ?? null,
                'application_id' => $flowData['application']['id'] ?? null,
                'application_name' => $flowData['application']['name'] ?? null,
                'application_link' => $flowData['application']['link'] ?? null,
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

    /**
     * Crea un nuevo builder de flujos (alias para createFlowBuilder)
     *
     * @param WhatsappBusinessAccount $account
     * @return FlowBuilder
     */
    public function builder(WhatsappBusinessAccount $account): FlowBuilder
    {
        return $this->createFlowBuilder($account);
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
                Log::channel('whatsapp')->error('Respuesta de flujo inválida', ['response' => $response]);
                return null;
            }

            return $this->storeOrUpdateFlow($account->whatsapp_business_id, $response);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al sincronizar flujo por ID: ' . $e->getMessage(), [
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
    public function syncScreensAndElements(WhatsappFlow $flow, array $screens): void
    {
        foreach ($screens as $screenData) {
            // Validar que el campo 'name' esté presente y no sea null
            if (empty($screenData['name'])) {
                throw new InvalidArgumentException("El campo 'name' es obligatorio para sincronizar las pantallas.");
            }

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
                        ]
                    );
                }
            }
        }
    }
}