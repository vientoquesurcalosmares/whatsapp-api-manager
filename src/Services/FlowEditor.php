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

    /**
     * JSON pre-encodificado del editor visual.
     * Cuando está seteado, save() lo usa directamente en lugar de buildFlowJson().
     * Se pasa como string para preservar objetos vacíos como {} (no como []).
     */
    protected ?string $rawJsonString = null;

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

    /**
     * Inyecta el JSON del flow ya encodificado como string.
     * Cuando se usa este método, save() omite buildFlowJson() y sube el JSON tal como está.
     * Preserva objetos vacíos {} correctamente — a diferencia de pasar un array PHP
     * que json_encode() convertiría a [].
     */
    public function setRawJsonStructure(string $jsonString): self
    {
        $this->rawJsonString = $jsonString;
        return $this;
    }

    // --- Guardar cambios en API y base de datos ---

    public function save(): Model
    {
        if (empty($this->flow->wa_flow_id)) {
            throw new InvalidArgumentException('El flujo no tiene wa_flow_id, no puede ser editado en la API.');
        }

        $flowId      = $this->flow->wa_flow_id;
        $accessToken = $this->flow->whatsappBusinessAccount->api_token;
        $headers     = ['Authorization' => 'Bearer ' . $accessToken];

        // 1. Actualizar metadatos del flow (nombre, categorías, descripción)
        //    Solo si hay nombre definido — en el flujo del editor visual esto es opcional.
        if (!empty($this->flowData['name'])) {
            $metaEndpoint = Endpoints::build(Endpoints::UPDATE_FLOW_METADATA, ['flow_id' => $flowId]);
            $this->apiClient->request('POST', $metaEndpoint, [], [
                'name'        => $this->flowData['name'],
                'categories'  => $this->flowData['categories'] ?? [],
                'description' => $this->flowData['description'] ?? '',
            ], [], $headers);
        }

        // 2. Determinar el JSON a subir:
        //    - rawJsonString: viene del editor visual, ya encodificado — preserva {} correctamente.
        //    - Sin rawJsonString: buildFlowJson() para el flujo builder (API fluida).
        if ($this->rawJsonString !== null) {
            $jsonString = $this->rawJsonString;
        } else {
            $flowJson = $this->buildFlowJson();
            if (empty($flowJson['screens'])) {
                throw new InvalidArgumentException('El flujo debe tener al menos una pantalla.');
            }
            $jsonString = json_encode($flowJson, JSON_UNESCAPED_UNICODE);
        }

        // 3. Subir el JSON vía ApiClient (multipart) — igual que FlowBuilder::save()
        $tmpFile = tempnam(sys_get_temp_dir(), 'flow_') . '.json';
        file_put_contents($tmpFile, $jsonString);

        try {
            $assetEndpoint = Endpoints::build(Endpoints::UPDATE_FLOW_ASSETS, ['flow_id' => $flowId]);
            $this->apiClient->request('POST', $assetEndpoint, [], [
                'multipart' => [
                    ['name' => 'name',       'contents' => 'flow.json'],
                    ['name' => 'asset_type', 'contents' => 'FLOW_JSON'],
                    [
                        'name'     => 'file',
                        'contents' => fopen($tmpFile, 'r'),
                        'filename' => 'flow.json',
                        'headers'  => ['Content-Type' => 'application/json'],
                    ],
                ],
            ], [], $headers);
        } finally {
            @unlink($tmpFile);
        }

        // 4. Actualizar la base de datos local
        $this->flow->update([
            'json_structure' => $jsonString,
        ]);

        Log::channel('whatsapp')->info('FlowEditor: JSON del flow actualizado en Meta', [
            'flow_id' => $flowId,
            'via_raw' => $this->rawJsonString !== null,
        ]);

        return $this->flow->fresh();
    }

    protected function buildFlowJson(): array
    {
        $screens = [];
        foreach ($this->flowData['json_structure']['screens'] as $screen) {
            if (empty($screen['name'])) {
                throw new InvalidArgumentException("El campo 'name' es obligatorio para construir el JSON del flujo.");
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