<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\{
    Flow,
    FlowStep,
    StepMessage,
    StepTransition,
    StepVariable
};
use ScriptDevelop\WhatsappManager\Enums\StepType;
use Illuminate\Support\Facades\Validator;

class StepBuilderService
{
    private $flow;
    private $step;
    private $messages = [];
    private $transitions = [];
    private $variables = [];
    private $validationRules = [];

    public function __construct(Flow $flow)
    {
        $this->flow = $flow;
    }

    /**
     * Crea un nuevo paso
     */
    public function createStep(string $name, StepType $type, ?string $description = null): self
    {
        $this->step = new FlowStep([
            'flow_id' => $this->flow->flow_id,
            'name' => $name,
            'step_type' => $type,
            'description' => $description,
            'order' => $this->getNextOrder()
        ]);

        // Resetear propiedades para evitar duplicados
        $this->messages = [];
        $this->transitions = [];
        $this->variables = [];
        $this->validationRules = [];

        return $this;
    }

    /**
     * Agrega mensaje de texto
     */
    public function addTextMessage(string $content, int $order = 1, int $delay = 0): self
    {
        $this->messages[] = [
            'type' => 'text',
            'content' => $content,
            'order' => $order,
            'delay' => $delay
        ];
        return $this;
    }

    /**
     * Agrega mensaje interactivo (botones)
     */
    public function addButtonMessage(string $body, array $buttons, ?string $footer = null, int $order = 1): self
    {
        $this->messages[] = [
            'type' => 'interactive_buttons',
            'parameters' => compact('body', 'buttons', 'footer'),
            'order' => $order
        ];
        return $this;
    }

    /**
     * Agrega variable a recolectar
     */
    public function addVariable(
        string $name, 
        string $type, 
        string $storageScope = 'global',
        array $validation = []
    ): self {
        $this->variables[] = compact('name', 'type', 'storageScope', 'validation');
        return $this;
    }

    /**
     * Configura reglas de validación
     */
    public function setValidationRules(array $rules, int $maxAttempts = 3, string $retryMessage = ''): self
    {
        $this->validationRules = compact('rules', 'maxAttempts', 'retryMessage');
        return $this;
    }

    /**
     * Agrega transición condicional
     */
    public function addConditionalTransition(
        string $targetStepId, 
        string $variable, 
        string $operator, 
        $value,
        int $priority = 1
    ): self {
        $this->transitions[] = [
            'type' => 'condition',
            'target_step_id' => $targetStepId,
            'condition' => compact('variable', 'operator', 'value'),
            'priority' => $priority
        ];
        return $this;
    }

    /**
     * Agrega transición directa
     */
    public function addDirectTransition(
        string $targetStepId,
        int $priority = 0
    ): self {
        $this->transitions[] = [
            'type' => 'direct',
            'target_step_id' => $targetStepId,
            'condition' => null,
            'priority' => $priority
        ];
        return $this;
    }

    /**
     * Configura acción para reintentos
     */
    public function setRetryAction(string $action, ?string $failureStepId = null): self
    {
        $this->step->failure_action = $action;
        $this->step->failure_step_id = $failureStepId;
        return $this;
    }

    /**
     * Construye y valida el paso
     */
    public function build(): FlowStep
    {
        $this->validateStepConfiguration();
        
        return \DB::transaction(function () {
            $this->step->save();

            $this->saveMessages();
            $this->saveVariables();
            $this->saveTransitions();
            
            return $this->step->refresh();
        });
    }

    private function validateStepConfiguration()
    {
        $type = $this->step->step_type;
        
        // Validar mensajes requeridos
        if (in_array($type, [StepType::CLOSED_QUESTION, StepType::CONDITIONAL]) && 
            !$this->hasInteractiveMessage()) {
            throw new \InvalidArgumentException(
                "Los pasos de tipo {$type} requieren mensajes interactivos"
            );
        }

        // Validar transiciones
        if ($type === StepType::TERMINAL && !empty($this->transitions)) {
            throw new \InvalidArgumentException(
                "Los pasos terminales no pueden tener transiciones"
            );
        }

        // Validar variables
        if ($type === StepType::OPEN_QUESTION && empty($this->variables)) {
            throw new \InvalidArgumentException(
                "Los pasos de pregunta abierta requieren al menos una variable"
            );
        }
    }

    private function hasInteractiveMessage(): bool
    {
        foreach ($this->messages as $message) {
            if (in_array($message['type'], ['interactive_buttons', 'interactive_list'])) {
                return true;
            }
        }
        return false;
    }

    private function saveStep()
    {
        // Configuración específica por tipo
        switch ($this->step->step_type) {
            case StepType::OPEN_QUESTION:
                $this->step->validation_rules = $this->validationRules;
                break;
            case StepType::API_CALL:
                // Configurar API (implementar según necesidades)
                break;
        }
        
        $this->step->save();
    }

    private function saveMessages()
    {
        foreach ($this->messages as $message) {
            // Verificar si el mensaje ya existe (opcional, depende del diseño)
            $exists = $this->step->messages()->where([
                'message_type' => $message['type'],
                'content' => $message['content'] ?? json_encode($message['parameters']),
                'order' => $message['order']
            ])->exists();

            if (!$exists) {
                $$this->step->messages()->create([
                    'message_type' => $message['type'],
                    'content' => $message['content'] ?? json_encode($message['parameters']),
                    'order' => $message['order'],
                    'delay_seconds' => $message['delay'] ?? 0
                ]);
            }
        }
    }

    private function saveVariables()
    {
        foreach ($this->variables as $variable) {
            StepVariable::create([
                'flow_step_id' => $this->step->step_id,
                'name' => $variable['name'],
                'type' => $variable['type'],
                'storage_scope' => $variable['storageScope'],
                'validation_rules' => $variable['validation']
            ]);
        }
    }

    private function saveTransitions()
    {
        foreach ($this->transitions as $transition) {
            StepTransition::create([
                'from_step_id' => $this->step->step_id,
                'to_step_id' => $transition['target_step_id'],
                'condition_type' => $transition['type'] === 'condition' 
                    ? 'variable_value' 
                    : 'always',
                'condition_config' => $transition['condition'] ?? null,
                'priority' => $transition['priority'] ?? 1
            ]);
        }
    }

    private function getNextOrder(): int
    {
        return FlowStep::where('flow_id', $this->flow->flow_id)->max('order') + 1;
    }
}