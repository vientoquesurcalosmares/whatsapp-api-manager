<?php

namespace ScriptDevelop\WhatsappManager\Services;

//use ScriptDevelop\WhatsappManager\Models\WhatsappFlow;
//use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
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

    /**
     * Establece el nombre del flujo
     */
    public function name(string $name): self
    {
        if (strlen($name) > 120) {
            throw new InvalidArgumentException(whatsapp_trans('messages.flow_name_max_length'));
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
            throw new InvalidArgumentException(whatsapp_trans('messages.flow_invalid_type', ['types' => implode(', ', $validTypes)]));
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
                whatsapp_trans('messages.flow_invalid_category', ['categories' => implode(', ', $validCategories)])
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
        if (empty($name)) {
            throw new InvalidArgumentException(whatsapp_trans('messages.flow_screen_name_required'));
        }

        // Finalizar la pantalla actual si existe
        if ($this->currentScreen) {
            $this->screens[] = $this->currentScreen->build();
        }

        // Crear una nueva pantalla con el nombre especificado
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
            throw new InvalidArgumentException(whatsapp_trans('messages.flow_name_required'));
        }

        // Construir estructura de pantallas en formato WhatsApp
        $whatsappScreens = [];
        foreach ($this->screens as $screen) {
            $whatsappScreens[] = $this->convertToWhatsappScreen($screen);
        }

        // Construir estructura final del flujo
        $this->flowData['json_structure'] = [
            'version' => '7.0', // Versión requerida por WhatsApp
            'screens' => $whatsappScreens,
            'metadata' => [
                'api_version' => '3.0',
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

        $this->flowData['endpoint_uri'] = config('app.url').'/whatsapp/flows/endpoint';
        $this->flowData['data_api_version'] = '3.0';

        return $this->flowData;
    }

    /**
     * Convierte una pantalla en formato WhatsApp
     */
    protected function convertToWhatsappScreen(array $screen): array
    {
        $children = [];
        $buttons = [];

        foreach ($screen['elements'] as $element) {
            if ($element['type'] === 'button') {
                $buttons[] = [
                    'type' => 'QuickReplyButton',
                    'title' => $element['label'] ?? 'Button',
                    'on_click_action' => [
                        'name' => $element['action']['name'] ?? 'complete',
                        'payload' => $element['action']['payload'] ?? (object)[],
                    ],
                ];
            } else {
                $children[] = $this->convertToWhatsappElement($element);
            }
        }

        // Agregar título y contenido como elementos separados
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

        // Agregar botones al final
        if (!empty($buttons)) {
            $children[] = [
                'type' => 'Footer',
                'buttons' => $buttons
            ];
        }

        return [
            'id' => strtoupper($screen['name']),
            'title' => $screen['title'] ?? '',
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => $children
            ],
            'data' => [
                'screen_id' => strtoupper($screen['name']),
                'title' => $screen['title'] ?? '',
                'content' => $screen['content'] ?? '',
            ]
        ];
    }

    protected function convertButton(array $element): array
    {
        return [
            'type' => 'QuickReplyButton',
            'title' => $element['label'] ?? 'Button',
            'on_click_action' => [
                'name' => $element['action']['name'] ?? 'complete',
                'payload' => $element['action']['payload'] ?? (object)[],
            ],
        ];
    }

    /**
     * Convierte un elemento en formato WhatsApp
     */
    protected function convertToWhatsappElement(array $element): array
    {
        $base = [
            'name' => $element['name'],
            'label' => $element['label'] ?? '',
            'data' => [
                'element_id' => $element['name'],
                'type' => $element['type'],
                'label' => $element['label'] ?? ''
            ]
        ];

        switch ($element['type']) {
            case 'input':
                $converted = array_merge($base, [
                    'type' => 'TextInput',
                    'placeholder' => $element['placeholder'] ?? '',
                    'required' => $element['required'] ?? false
                ]);
                break;

            case 'dropdown':
                $converted = array_merge($base, [
                    'type' => 'Dropdown',
                    'options' => array_map(function($option) {
                        return [
                            'id' => $option['value'] ?? '',
                            'title' => $option['label'] ?? ''
                        ];
                    }, $element['options'] ?? [])
                ]);
                break;

            case 'checkbox':
                $converted = array_merge($base, [
                    'type' => 'Checkbox',
                    'required' => $element['required'] ?? false
                ]);
                break;

            case 'button':
                $converted = [
                    'type' => 'QuickReplyButton',
                    'title' => $element['label'] ?? 'Button',
                    'on_click_action' => [
                        'name' => $element['action']['name'] ?? 'complete',
                        'payload' => $element['action']['payload'] ?? (object)[],
                    ],
                ];
                break;

            default:
                throw new InvalidArgumentException("Tipo de elemento no soportado: {$element['type']}");
        }

        // Limpieza de valores nulos
        return array_filter($converted, function($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Guarda el flujo en la API y base de datos
     */
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
            $response = $this->apiClient->request(
                'POST',
                $endpoint,
                [],
                [
                        'name' => $flowData['name'],
                        'categories' => $flowData['categories'],
                        'endpoint_uri' => $flowData['endpoint_uri'],
                        'data_api_version' => $flowData['data_api_version'],
                        'json' => $flowData['json'],
                    ],
                [],
                $headers
            );

            // Crear registro en base de datos
            $flow = WhatsappModelResolver::flow()->create([
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
            // $screens = $flowData['json_structure']['screens'] ?? [];
            $screens = $this->screens;

            $this->flowService->syncScreensAndElements($flow, $screens);

            return $flow;

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error(whatsapp_trans('messages.flow_error_saving', ['message' => $e->getMessage()]), [
                'endpoint' => $endpoint,
                'flow_data' => $flowData
            ]);
            throw $e;
        }
    }
}