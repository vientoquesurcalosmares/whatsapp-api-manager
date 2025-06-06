<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\WhatsappFlow;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class FlowBuilder
{
    protected array $flowData = [];
    protected ApiClient $apiClient;
    protected WhatsappBusinessAccount $account;
    protected FlowService $flowService;

    public function __construct(ApiClient $apiClient, WhatsappBusinessAccount $account, FlowService $flowService)
    {
        $this->apiClient = $apiClient;
        $this->account = $account;
        $this->flowService = $flowService;
    }

    public function setName(string $name): self
    {
        if (strlen($name) > 120) {
            throw new InvalidArgumentException('El nombre del flujo no puede exceder los 120 caracteres.');
        }
        $this->flowData['name'] = $name;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->flowData['description'] = $description;
        return $this;
    }

    public function setFlowType(string $type): self
    {
        $validTypes = ['AUTHENTICATION', 'MARKETING', 'UTILITY', 'SERVICE'];
        if (!in_array($type, $validTypes)) {
            throw new InvalidArgumentException('Tipo de flujo inválido. Valores permitidos: ' . implode(', ', $validTypes));
        }
        $this->flowData['flow_type'] = $type;
        return $this;
    }

    public function setJsonStructure(array $structure): self
    {
        $this->flowData['json_structure'] = $structure;
        return $this;
    }

    /**
     * Agrega una pantalla al flujo
     */
    public function addScreen(array $screenData): self
    {
        // Validar estructura básica de pantalla
        $requiredKeys = ['name', 'title', 'content', 'elements'];
        foreach ($requiredKeys as $key) {
            if (!isset($screenData[$key])) {
                throw new InvalidArgumentException("La pantalla requiere la clave: $key");
            }
        }

        $this->flowData['screens'][] = $screenData;
        return $this;
    }

    /**
     * Construye la estructura final del flujo
     */
    public function build(): array
    {
        // Validación mínima
        if (empty($this->flowData['name'])) {
            throw new InvalidArgumentException('El nombre del flujo es obligatorio.');
        }

        if (empty($this->flowData['json_structure']) && empty($this->flowData['screens'])) {
            throw new InvalidArgumentException('Debe proporcionar una estructura JSON o pantallas para el flujo.');
        }

        // Construir estructura si se usó el método de pantallas
        if (!empty($this->flowData['screens'])) {
            $this->flowData['json_structure'] = [
                'version' => '1.0',
                'screens' => $this->flowData['screens'],
                'metadata' => [
                    'created_at' => now()->toISOString(),
                    'last_updated' => now()->toISOString()
                ]
            ];
            unset($this->flowData['screens']);
        }

        return $this->flowData;
    }

    /**
     * Guarda el flujo en la API y base de datos
     */
    public function save(): WhatsappFlow
    {
        $flowData = $this->build();

        $endpoint = Endpoints::build(Endpoints::CREATE_FLOW, [
            'waba_id' => $this->account->whatsapp_business_id,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $this->account->api_token,
            'Content-Type' => 'application/json',
        ];

        try {
            $response = $this->apiClient->request(
                'POST',
                $endpoint,
                [],
                $flowData,
                [],
                $headers
            );

            // Crear registro en base de datos
            $flow = WhatsappFlow::create([
                'whatsapp_business_account_id' => $this->account->whatsapp_business_id,
                'wa_flow_id' => $response['id'],
                'name' => $flowData['name'],
                'flow_type' => $flowData['flow_type'] ?? 'UNKNOWN',
                'description' => $flowData['description'] ?? '',
                'json_structure' => $flowData['json_structure'],
                'status' => $response['status'] ?? 'DRAFT',
                'version' => $response['version'] ?? '1.0',
            ]);

            return $flow;

        } catch (\Exception $e) {
            Log::error('Error al guardar flujo: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'flow_data' => $flowData
            ]);
            throw $e;
        }
    }
}