<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\WhatsappFlow;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Servicio para edición fluida de flujos de WhatsApp.
 */
class FlowEditor
{
    protected WhatsappFlow $flow;
    protected ApiClient $apiClient;
    protected FlowService $flowService;
    protected array $flowData;
    protected ?array $currentScreen = null;
    protected ?int $currentScreenIndex = null;

    public function __construct(WhatsappFlow $flow, ApiClient $apiClient, FlowService $flowService)
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

    public function save(): WhatsappFlow
    {
        if (empty($this->flow->wa_flow_id)) {
            throw new InvalidArgumentException('El flujo no tiene wa_flow_id, no puede ser editado en la API.');
        }

        $endpoint = Endpoints::build(Endpoints::GET_FLOW, [
            'flow_id' => $this->flow->wa_flow_id,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $this->flow->whatsappBusinessAccount->api_token,
            'Content-Type' => 'application/json',
        ];

        // Enviar actualización a la API de WhatsApp
        $response = $this->apiClient->request(
            'POST', // O 'PATCH' según la API
            $endpoint,
            [],
            $this->flowData,
            [],
            $headers
        );

        // Actualizar la base de datos local con la respuesta de la API
        $this->flow->update([
            'name' => $this->flowData['name'],
            'description' => $this->flowData['description'],
            'json_structure' => $this->flowData['json_structure'],
            'status' => $response['status'] ?? $this->flow->status,
            'version' => $response['version'] ?? $this->flow->version,
            // ...otros campos según respuesta...
        ]);

        // Si editaste screens/elements, sincronízalos también
        if (!empty($this->flowData['json_structure']['screens'])) {
            $this->flowService->syncScreensAndElements($this->flow, $this->flowData['json_structure']['screens']);
        }

        return $this->flow->fresh();
    }
}