<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;
use InvalidArgumentException;

class FlowBuilder
{
    protected array $flowData = [];
    protected array $screens = [];
    protected ?ScreenBuilder $currentScreen = null;
    protected ApiClient $apiClient;
    protected Model $account;
    protected FlowService $flowService;

    public function __construct(ApiClient $apiClient, Model $account, FlowService $flowService)
    {
        $this->apiClient = $apiClient;
        $this->account = $account;
        $this->flowService = $flowService;
    }

    // ==========================================
    // Metadatos del Flujo
    // ==========================================
    public function name(string $name): self
    {
        if (strlen($name) > 120) {
            throw new InvalidArgumentException('El nombre del flujo no puede exceder los 120 caracteres.');
        }
        $this->flowData['name'] = $name;
        return $this;
    }

    public function description(string $description): self
    {
        $this->flowData['description'] = $description;
        return $this;
    }

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
            'SIGN_UP',
            'SIGN_IN',
            'APPOINTMENT_BOOKING',
            'LEAD_GENERATION',
            'SHOPPING',
            'CONTACT_US',
            'CUSTOMER_SUPPORT',
            'SURVEY',
            'OTHER'
        ];
        if (!in_array($category, $validCategories)) {
            throw new InvalidArgumentException(
                'Categoría inválida. Valores permitidos: ' . implode(', ', $validCategories)
            );
        }
        $this->flowData['categories'] = [$category];
        return $this;
    }

    /**
     * Permite inyectar una estructura JSON avanzada completa.
     * Si se usa, el método build() saltará la compilación de pantallas fluidas.
     */
    public function setJsonStructure(array $structure): self
    {
        $this->flowData['json_structure'] = $structure;
        return $this;
    }

    public function routingModel(array $routingModel): self
    {
        $this->flowData['routing_model'] = $routingModel;
        return $this;
    }

    // ==========================================
    // Gestión de Pantallas (Fluent API)
    // ==========================================
    public function screen(string $name): ScreenBuilder
    {
        if (empty($name)) {
            throw new InvalidArgumentException('El nombre de la pantalla es obligatorio.');
        }

        // Finalizar la pantalla actual si existe
        if ($this->currentScreen) {
            $this->screens[] = $this->currentScreen->build();
        }

        // Crear una nueva pantalla con el nombre especificado
        $this->currentScreen = new ScreenBuilder($this, $name);
        return $this->currentScreen;
    }

    public function addScreen(array $screenData): self
    {
        $this->screens[] = $screenData;
        return $this;
    }

    // ==========================================
    // Compilación a Formato WhatsApp (Meta API)
    // ==========================================
    public function build(): array
    {
        // 1. Si el desarrollador inyectó su propio JSON completo, lo respetamos
        if (!empty($this->flowData['json_structure']['screens'])) {
            $this->flowData['endpoint_uri'] = config('app.url') . '/whatsapp/flows/endpoint';
            $this->flowData['data_api_version'] = '3.0';

            if (empty($this->flowData['categories'])) {
                $this->flowData['categories'] = ['OTHER'];
            }
            return $this->flowData;
        }

        // 2. Si usamos la API Fluida, cerramos la pantalla en curso
        if ($this->currentScreen) {
            $this->screens[] = $this->currentScreen->build();
            $this->currentScreen = null;
        }

        if (empty($this->flowData['name'])) {
            throw new InvalidArgumentException('El nombre del flujo es obligatorio.');
        }

        $whatsappScreens = [];
        $screenIds = [];
        foreach ($this->screens as $screen) {
            $whatsappScreens[] = $this->convertToWhatsappScreen($screen);
            $screenIds[] = strtoupper($screen['name']);
        }

        $routingModel = $this->flowData['routing_model'] ?? [];
        if (empty($routingModel) && count($screenIds) > 0) {
            for ($i = 0; $i < count($screenIds) - 1; $i++) {
                $routingModel[$screenIds[$i]] = [$screenIds[$i + 1]];
            }
            $routingModel[$screenIds[count($screenIds) - 1]] = [];
        }

        $this->flowData['json_structure'] = [
            'version' => '7.3',
            'data_api_version' => '3.0',
            'routing_model' => empty($routingModel) ? new \stdClass() : $routingModel,
            'screens' => $whatsappScreens,
        ];

        if (empty($this->flowData['categories'])) {
            $typeToCategory = [
                'AUTHENTICATION' => 'SIGN_IN',
                'MARKETING' => 'LEAD_GENERATION',
                'UTILITY' => 'CUSTOMER_SUPPORT',
                'SERVICE' => 'CUSTOMER_SUPPORT',
            ];
            $flowType = $this->flowData['flow_type'] ?? 'UTILITY';
            $category = $typeToCategory[$flowType] ?? 'OTHER';
            $this->flowData['categories'] = [$category];
        }

        $this->flowData['endpoint_uri'] = config('app.url') . '/whatsapp/flows/endpoint';
        $this->flowData['data_api_version'] = '3.0';

        return $this->flowData;
    }

    protected function convertToWhatsappScreen(array $screen): array
    {
        $children = [];
        $buttons = [];

        foreach ($screen['elements'] ?? [] as $element) {
            if ($element['type'] === 'button') {
                $buttons[] = $element;
            } else {
                $children[] = $this->convertToWhatsappElement($element);
            }
        }

        if (!empty($screen['title'])) {
            array_unshift($children, [
                'type' => 'TextHeading',
                'text' => $screen['title']
            ]);
        }

        if (!empty($screen['content'])) {
            array_unshift($children, [
                'type' => 'TextBody',
                'text' => $screen['content']
            ]);
        }

        // NUEVO FORMATO DE FOOTER (v3.0+)
        if (!empty($buttons)) {
            $primaryBtn = $buttons[0];
            $children[] = [
                'type' => 'Footer',
                'label' => $primaryBtn['label'] ?? 'Continuar',
                'on-click-action' => $primaryBtn['action'] ?? [
                    'name' => 'complete',
                    'payload' => (object) []
                ]
            ];
        }

        // Construir la pantalla base
        $whatsappScreen = [
            'id' => strtoupper($screen['name']),
            'title' => $screen['title'] ?? '',
            'data' => !empty($screen['data']) ? $screen['data'] : (object) [],
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => $children
            ]
        ];

        // Agregar banderas opcionales si existen (terminal, success)
        if (isset($screen['terminal'])) {
            $whatsappScreen['terminal'] = $screen['terminal'];
        }
        if (isset($screen['success'])) {
            $whatsappScreen['success'] = $screen['success'];
        }

        return $whatsappScreen;
    }

    protected function convertToWhatsappElement(array $element): array
    {
        // ElementBuilder ya construye las llaves casi perfectas (ej. 'input-type', 'data-source')
        // Aquí solo hacemos el mapeo final del tipo de componente y limpiamos variables basura.

        $base = $element;
        unset($base['name']); // Name se usa internamente o como id en inputs, lo procesaremos

        switch ($element['type']) {
            case 'input':
                $base['type'] = 'TextInput';
                $base['name'] = $element['name'];

                // MEJORA DEFENSIVA: Si el dev usó placeholder por costumbre, lo pasamos a helper-text o lo borramos.
                if (isset($base['placeholder'])) {
                    if (!isset($base['helper-text'])) {
                        $base['helper-text'] = $base['placeholder'];
                    }
                    unset($base['placeholder']); // Meta odia esto, lo borramos.
                }
                break;

            case 'dropdown':
                $base['type'] = 'Dropdown';
                $base['name'] = $element['name'];
                if (isset($base['options'])) {
                    $base['options'] = array_map(function ($option) {
                        return [
                            'id' => $option['value'] ?? '',
                            'title' => $option['label'] ?? ''
                        ];
                    }, $base['options']);
                }
                break;

            case 'checkbox':
                $base['type'] = 'Checkbox';
                $base['name'] = $element['name'];
                break;

            // Componentes que pasan casi idénticos
            case 'TextHeading':
            case 'TextSubheading':
            case 'TextBody':
            case 'TextCaption':
            case 'RadioButtonsGroup':
            case 'If':
                // ElementBuilder ya los preparó correctamente, solo aseguramos el 'name' en Radio
                if ($element['type'] === 'RadioButtonsGroup') {
                    $base['name'] = $element['name'];
                }
                break;

            default:
                throw new InvalidArgumentException("Tipo de elemento no soportado o mapeo faltante: {$element['type']}");
        }

        // Limpieza estricta de nulos para no enviar basura a Meta
        return array_filter($base, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    // ==========================================
    // Guardado y Comunicación con la API
    // ==========================================
    public function save(): Model
    {
        $flowData = $this->build();

        $flowData['json'] = json_encode($flowData['json_structure'], JSON_UNESCAPED_UNICODE);

        $endpoint = Endpoints::build(Endpoints::CREATE_FLOW, [
            'waba_id' => $this->account->whatsapp_business_id,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $this->account->api_token,
            'Content-Type' => 'application/json',
        ];

        try {
            // 1. Crear el flujo
            $response = $this->apiClient->request(
                'POST',
                $endpoint,
                [],
                [
                    'name' => $flowData['name'],
                    'categories' => $flowData['categories'],
                ],
                [],
                $headers
            );

            $flowId = $response['id'];

            // 2. Subir el JSON (Componentes) del flujo
            $assetEndpoint = Endpoints::build(Endpoints::UPDATE_FLOW_ASSETS, [
                'flow_id' => $flowId,
            ]);

            $multipartData = [
                'multipart' => [
                    [
                        'name' => 'name',
                        'contents' => 'flow.json'
                    ],
                    [
                        'name' => 'asset_type',
                        'contents' => 'FLOW_JSON'
                    ],
                    [
                        'name' => 'file',
                        'contents' => $flowData['json'],
                        'filename' => 'flow.json',
                        'headers' => ['Content-Type' => 'application/json']
                    ]
                ]
            ];

            $multipartHeaders = [
                'Authorization' => 'Bearer ' . $this->account->api_token,
            ];

            $assetResponse = $this->apiClient->request(
                'POST',
                $assetEndpoint,
                [],
                $multipartData,
                [],
                $multipartHeaders
            );

            // 3. Opcional: Configurar endpoint_uri si se requiere
            try {
                $metaEndpoint = Endpoints::build(Endpoints::UPDATE_FLOW_METADATA, [
                    'flow_id' => $flowId,
                ]);

                $this->apiClient->request(
                    'POST',
                    $metaEndpoint,
                    [],
                    [
                        'endpoint_uri' => $flowData['endpoint_uri'],
                    ],
                    [],
                    $headers
                );
            } catch (\Exception $metaEx) {
                Log::channel('whatsapp')->warning('No se pudo configurar el endpoint_uri del flujo', [
                    'error' => $metaEx->getMessage()
                ]);
            }

            // Crear registro en base de datos local
            $flow = WhatsappModelResolver::flow()->create([
                'whatsapp_business_account_id' => $this->account->whatsapp_business_id,
                'wa_flow_id' => $flowId,
                'name' => $flowData['name'],
                'flow_type' => $flowData['flow_type'] ?? 'UNKNOWN',
                'description' => $flowData['description'] ?? '',
                'json_structure' => $flowData['json_structure'],
                'status' => $response['status'] ?? 'DRAFT',
                'version' => $response['version'] ?? '1.0',
                'categories' => $response['categories'] ?? null,
                'preview_url' => $response['preview']['preview_url'] ?? null,
                'preview_expires_at' => $response['preview']['expires_at'] ?? null,
                'validation_errors' => $assetResponse['validation_errors'] ?? ($response['validation_errors'] ?? null),
                'json_version' => $assetResponse['json_version'] ?? ($response['json_version'] ?? null),
                'health_status' => $response['health_status'] ?? null,
                'application_id' => $response['application']['id'] ?? null,
                'application_name' => $response['application']['name'] ?? null,
                'application_link' => $response['application']['link'] ?? null,
            ]);

            $screens = $this->screens;
            $this->flowService->syncScreensAndElements($flow, $screens);

            return $flow;

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al guardar flujo: ' . $e->getMessage(), [
                'endpoint' => $endpoint ?? 'N/A',
                'flow_data' => $flowData
            ]);
            throw $e;
        }
    }
}