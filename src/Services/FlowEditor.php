<?php

namespace ScriptDevelop\WhatsappManager\Services;

//use ScriptDevelop\WhatsappManager\Models\WhatsappFlow;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use Illuminate\Support\Facades\Log;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

use InvalidArgumentException;

/**
 * Servicio para edición fluida de flujos de WhatsApp.
 */
class FlowEditor
{
    protected Model $flow;
    protected ApiClient $apiClient;
    protected FlowService $flowService;
    protected array $flowData;
    protected ?array $currentScreen = null;
    protected ?int $currentScreenIndex = null;
    protected ?array $currentElement = null;
    protected ?int $currentElementIndex = null;

    public function __construct(Model $flow, ApiClient $apiClient, FlowService $flowService)
    {
        $this->flow = $flow;
        $this->apiClient = $apiClient;
        $this->flowService = $flowService;
        $this->flowData = $this->loadFlowData();
    }

    protected function loadFlowData(): array
    {
        $data = $this->flow->toArray();
        if (is_array($this->flow->json_structure)) {
            $data['json_structure'] = $this->flow->json_structure;
        } elseif (!empty($this->flow->json_structure)) {
            $data['json_structure'] = json_decode($this->flow->json_structure, true);
        } else {
            $data['json_structure'] = ['screens' => []];
        }
        return $data;
    }

    // --- Métodos de edición fluida ---

    public function name(string $name): self
    {
        $this->flowData['name'] = $name;
        return $this;
    }

    public function description(string $description): self
    {
        $this->flowData['description'] = $description;
        return $this;
    }

    public function category(string $category): self
    {
        $this->flowData['categories'] = [$category];
        return $this;
    }

    public function screen(string $name): self
    {
        $screens = &$this->flowData['json_structure']['screens'];
        foreach ($screens as $i => $screen) {
            if ($screen['name'] === $name) {
                $this->currentScreen = &$screens[$i];
                $this->currentScreenIndex = $i;
                return $this;
            }
        }
        // Si no existe, crear nueva pantalla
        $newScreen = [
            'name' => $name,
            'title' => '',
            'content' => '',
            'is_start' => false,
            'order' => count($screens) + 1,
            'elements' => [],
        ];
        $screens[] = $newScreen;
        $this->currentScreen = &$screens[array_key_last($screens)];
        $this->currentScreenIndex = array_key_last($screens);
        return $this;
    }

    public function title(string $title): self
    {
        if ($this->currentScreen !== null) {
            $this->flowData['json_structure']['screens'][$this->currentScreenIndex]['title'] = $title;
        }
        return $this;
    }

    public function content(string $content): self
    {
        if ($this->currentScreen !== null) {
            $this->flowData['json_structure']['screens'][$this->currentScreenIndex]['content'] = $content;
        }
        return $this;
    }

    public function isStart(bool $isStart): self
    {
        if ($this->currentScreen !== null) {
            $this->flowData['json_structure']['screens'][$this->currentScreenIndex]['is_start'] = $isStart;
        }
        return $this;
    }

    public function order(int $order): self
    {
        if ($this->currentScreen !== null) {
            $this->flowData['json_structure']['screens'][$this->currentScreenIndex]['order'] = $order;
        }
        return $this;
    }

    public function element(string $name): self
    {
        if ($this->currentScreen !== null) {
            $elements = &$this->flowData['json_structure']['screens'][$this->currentScreenIndex]['elements'];
            foreach ($elements as $i => $el) {
                if ($el['name'] === $name) {
                    $this->currentElement = &$elements[$i];
                    $this->currentElementIndex = $i;
                    return $this;
                }
            }
            // Si no existe, crear nuevo elemento
            $newElement = [
                'name' => $name,
                'type' => '',
                'label' => '',
            ];
            $elements[] = $newElement;
            $this->currentElement = &$elements[array_key_last($elements)];
            $this->currentElementIndex = array_key_last($elements);
        }
        return $this;
    }

    public function type(string $type): self
    {
        if (isset($this->currentElement)) {
            $this->currentElement['type'] = $type;
        }
        return $this;
    }

    public function label(string $label): self
    {
        if (isset($this->currentElement)) {
            $this->currentElement['label'] = $label;
        }
        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        if (isset($this->currentElement)) {
            $this->currentElement['placeholder'] = $placeholder;
        }
        return $this;
    }

    public function required(bool $required): self
    {
        if (isset($this->currentElement)) {
            $this->currentElement['required'] = $required;
        }
        return $this;
    }

    public function endElement(): self
    {
        unset($this->currentElement, $this->currentElementIndex);
        return $this;
    }

    public function endScreen(): self
    {
        unset($this->currentScreen, $this->currentScreenIndex);
        return $this;
    }

    public function removeScreen(string $name): self
    {
        $screens = &$this->flowData['json_structure']['screens'];
        $screens = array_values(array_filter($screens, fn($s) => $s['name'] !== $name));
        return $this;
    }

    public function removeElement(string $elementName): self
    {
        if ($this->currentScreen !== null) {
            $elements = &$this->flowData['json_structure']['screens'][$this->currentScreenIndex]['elements'];
            $elements = array_values(array_filter($elements, fn($el) => $el['name'] !== $elementName));
        }
        return $this;
    }

    public function reorderScreens(array $names): self
    {
        $screens = &$this->flowData['json_structure']['screens'];
        usort($screens, function($a, $b) use ($names) {
            return array_search($a['name'], $names) <=> array_search($b['name'], $names);
        });
        // Reasignar el campo 'order'
        foreach ($screens as $i => &$screen) {
            $screen['order'] = $i + 1;
        }
        return $this;
    }

    // --- Guardar cambios en API y base de datos ---

    public function save(): Model
    {
        if (empty($this->flow->wa_flow_id)) {
            throw new InvalidArgumentException(whatsapp_trans('messages.flow_no_wa_flow_id_cannot_edit'));
        }

        $flowId = $this->flow->wa_flow_id;
        $accessToken = $this->flow->whatsappBusinessAccount->api_token;

        // 1. Actualizar metadatos
        $metaEndpoint = Endpoints::build(Endpoints::UPDATE_FLOW_METADATA, [
            'flow_id' => $flowId,
        ]);
        $metaData = [
            'name' => $this->flowData['name'],
            'categories' => $this->flowData['categories'] ?? [],
            'description' => $this->flowData['description'] ?? '',
        ];
        $this->apiClient->request(
            'POST',
            $metaEndpoint,
            [],
            $metaData,
            [],
            [
                'Authorization' => 'Bearer ' . $accessToken,
            ]
        );

        // 2. Actualizar JSON del flujo
        $flowJson = $this->buildFlowJson();
        $this->flowData['json_structure'] = $flowJson;

        if (empty($flowJson['screens'])) {
            throw new InvalidArgumentException(whatsapp_trans('messages.flow_must_have_at_least_one_screen'));
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'flow_') . '.json';
        file_put_contents($tmpFile, json_encode($flowJson));

        $baseUrl = config('whatsapp.api.base_url', 'https://graph.facebook.com');
        $version = config('whatsapp.api.version', 'v22.0');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($version, '/') . '/' . $flowId . '/assets';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($tmpFile, 'application/json', 'flow.json'),
                'name' => 'flow.json',
                'asset_type' => 'FLOW_JSON',
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);
        unlink($tmpFile);

        if ($curlError) {
            throw new \RuntimeException("Error en cURL: $curlError");
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['error'])) {
            throw new \RuntimeException("Error al subir el flujo: " . $decoded['error']['message']);
        }

        // 3. Actualizar la base de datos local
        $this->flow->update([
            'name' => $this->flowData['name'],
            'description' => $this->flowData['description'],
            'json_structure' => json_encode($flowJson),
            'status' => $this->flowData['status'] ?? 'draft',
            'version' => $this->flowData['version'] ?? '7.0',
            'categories' => $this->flowData['categories'] ?? [],
            'preview_url' => $this->flowData['preview_url'] ?? null,
            'preview_expires_at' => $this->flowData['preview_expires_at'] ?? null,
            'validation_errors' => $this->flowData['validation_errors'] ?? [],
            'json_version' => $this->flowData['json_version'] ?? '7.0',
            'health_status' => $this->flowData['health_status'] ?? [],
        ]);

        if (!empty($this->flowData['json_structure']['screens'])) {
            $this->flowService->syncScreensAndElements($this->flow, $this->flowData['json_structure']['screens']);
        }

        return $this->flow->fresh();
    }

    protected function buildFlowJson(): array
    {
        $screens = [];
        foreach ($this->flowData['json_structure']['screens'] as $screen) {
            if (empty($screen['name'])) {
                throw new InvalidArgumentException(whatsapp_trans('messages.flow_screen_name_required'));
            }

            $children = [];
            // Construir elementos válidos
            foreach ($screen['elements'] ?? [] as $element) {
                // Lógica para construir elementos...
            }

            $screens[] = [
                'id' => strtoupper($screen['name']),
                'title' => $screen['title'] ?? '',
                'layout' => [
                    'type' => 'SingleColumnLayout',
                    'children' => $children,
                ],
                'data' => (object)[],
            ];
        }

        return [
            'version' => $this->flowData['json_version'] ?? '7.0',
            'screens' => $screens,
        ];
    }
}