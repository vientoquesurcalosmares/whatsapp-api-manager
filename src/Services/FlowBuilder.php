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
    protected array $screens = [];
    protected ?ScreenBuilder $currentScreen = null;
    protected ApiClient $apiClient;
    protected WhatsappBusinessAccount $account;
    protected FlowService $flowService;

    public function __construct(ApiClient $apiClient, WhatsappBusinessAccount $account, FlowService $flowService)
    {
        $this->apiClient = $apiClient;
        $this->account = $account;
        $this->flowService = $flowService;
    }

    /**
     * Establece el nombre del flujo
     */
    public function name(string $name): self
    {
        if (strlen($name) > 120) {
            throw new InvalidArgumentException('El nombre del flujo no puede exceder los 120 caracteres.');
        }
        $this->flowData['name'] = $name;
        return $this;
    }

    /**
     * Establece la descripción del flujo
     */
    public function description(string $description): self
    {
        $this->flowData['description'] = $description;
        return $this;
    }

    /**
     * Establece el tipo de flujo
     */
    public function type(string $type): self
    {
        $validTypes = ['AUTHENTICATION', 'MARKETING', 'UTILITY', 'SERVICE'];
        if (!in_array($type, $validTypes)) {
            throw new InvalidArgumentException('Tipo de flujo inválido. Valores permitidos: ' . implode(', ', $validTypes));
        }
        $this->flowData['flow_type'] = $type;
        return $this;
    }

    public function category(string $category): self
    {
        $validCategories = [
            'SIGN_UP', 'SIGN_IN', 'APPOINTMENT_BOOKING', 'LEAD_GENERATION',
            'SHOPPING', 'CONTACT_US', 'CUSTOMER_SUPPORT', 'SURVEY', 'OTHER'
        ];
        if (!in_array($category, $validCategories)) {
            throw new InvalidArgumentException(
                'Categoría inválida. Valores permitidos: ' . implode(', ', $validCategories)
            );
        }
        $this->flowData['categories'] = [$category];
        return $this;
    }

    public function setJsonStructure(array $structure): self
    {
        $this->flowData['json_structure'] = $structure;
        return $this;
    }

    /**
     * Comienza a construir una nueva pantalla
     */
    public function screen(string $name): ScreenBuilder
    {
        // Si ya hay una pantalla en construcción, la guardamos
        if ($this->currentScreen) {
            $this->screens[] = $this->currentScreen->build();
        }

        $this->currentScreen = new ScreenBuilder($this, $name);
        return $this->currentScreen;
    }

    /**
     * Agrega una pantalla construida directamente
     */
    public function addScreen(array $screenData): self
    {
        $this->screens[] = $screenData;
        return $this;
    }

    /**
     * Construye la estructura final del flujo
     */
    public function build(): array
    {
        // Si hay una pantalla en construcción, la agregamos
        if ($this->currentScreen) {
            $this->screens[] = $this->currentScreen->build();
            $this->currentScreen = null;
        }

        // Validación mínima
        if (empty($this->flowData['name'])) {
            throw new InvalidArgumentException('El nombre del flujo es obligatorio.');
        }

        // Construir estructura de pantallas
        $this->flowData['json_structure'] = [
            'version' => '1.0',
            'screens' => $this->screens,
            'metadata' => [
                'created_at' => now()->toISOString(),
                'last_updated' => now()->toISOString()
            ]
        ];

        // Asegurar que categories esté presente y sea un array
        if (empty($this->flowData['categories'])) {
            $typeToCategory = [
                'AUTHENTICATION' => 'SIGN_IN',
                'MARKETING'      => 'LEAD_GENERATION',
                'UTILITY'        => 'CUSTOMER_SUPPORT',
                'SERVICE'        => 'CUSTOMER_SUPPORT',
            ];
            $flowType = $this->flowData['flow_type'] ?? 'UTILITY';
            $category = $typeToCategory[$flowType] ?? 'OTHER';
            $this->flowData['categories'] = [$category];
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
                'categories' => $response['categories'] ?? null,
                'preview_url' => $response['preview']['preview_url'] ?? null,
                'preview_expires_at' => $response['preview']['expires_at'] ?? null,
                'validation_errors' => $response['validation_errors'] ?? null,
                'json_version' => $response['json_version'] ?? null,
                'health_status' => $response['health_status'] ?? null,
                'application_id' => $response['application']['id'] ?? null,
                'application_name' => $response['application']['name'] ?? null,
                'application_link' => $response['application']['link'] ?? null,
            ]);

            // Sincronizar screens y elements en la base de datos
            $screens = $flowData['json_structure']['screens'] ?? [];
            $this->flowService->syncScreensAndElements($flow, $screens);

            return $flow;

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al guardar flujo: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'flow_data' => $flowData
            ]);
            throw $e;
        }
    }
}