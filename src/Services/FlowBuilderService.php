<?php
namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\{
    Flow,
    FlowStep,
    FlowTrigger,
    FlowVariable,
    StepMessage,
    StepVariable
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class FlowBuilderService
{
    // Tipos de paso permitidos
    const ALLOWED_STEP_TYPES = [
        'message_sequence',
        'open_question',
        'closed_question',
        'conditional',
        'terminal',
        'api_call'
    ];

    // Tipos de trigger permitidos
    const ALLOWED_TRIGGER_TYPES = ['keyword', 'template', 'hybrid'];

    /**
     * Crea un nuevo flujo con sus triggers iniciales.
     */
    public function createFlow(array $data): Flow
    {
        return DB::transaction(function () use ($data) {
            $isCaseSensitive = (bool)Arr::get($data, 'is_case_sensitive', false);

            $flow = Flow::create([
                'name'              => Arr::get($data, 'name'),
                'description'       => Arr::get($data, 'description'),
                'type'              => Arr::get($data, 'type'),
                'trigger_keywords' => $this->normalizeKeywords(
                Arr::get($data, 'trigger_keywords', []),
                $isCaseSensitive),
                'is_case_sensitive' => $isCaseSensitive,
                'is_default'        => Arr::get($data, 'is_default', false),
                'is_active'         => Arr::get($data, 'is_active', true),
            ]);

            // Triggers
            foreach ($data['triggers'] ?? [] as $trigger) {
                $this->addTrigger($flow->flow_id, $trigger);
            }

            // Variables globales
            foreach ($data['variables'] ?? [] as $variable) {
                $this->addFlowVariable($flow->flow_id, $variable);
            }

            // Pasos
            foreach ($data['steps'] ?? [] as $stepData) {
                $this->addStep($flow->flow_id, $stepData);
            }

            return $flow->load(['triggers', 'variables', 'steps']);
        });
    }

    public function createEmptyFlow(string $name, string $type = 'inbound'): Flow
    {
        return Flow::create([
            'name' => $this->validateName($name),
            'type' => $this->validateFlowType($type),
            'is_active' => true
        ]);
    }

    /**
     * Agrega un paso al flujo.
     */
    public function addStep(string $flowId, array $stepData): FlowStep
    {
        $maxOrder = FlowStep::where('flow_id', $flowId)->max('order') ?? 0;
        
        return DB::transaction(function () use ($flowId, $stepData) {
            $step = FlowStep::create([
                'flow_id' => $flowId,
                'step_type' => $this->validateStepType($stepData['type']),
                'validation_rules' => $this->parseValidationRules($stepData['validation'] ?? []),
                'max_attempts' => $stepData['max_attempts'] ?? 1,
                'retry_message' => $stepData['retry_message'] ?? null,
                'failure_action' => $this->validateFailureAction($stepData['failure_action'] ?? 'end_flow'),
                'failure_step_id' => $stepData['failure_step_id'] ?? null,
                'is_terminal' => (bool)($stepData['is_terminal'] ?? false),
            ]);

            // Mensajes asociados
            foreach ($stepData['messages'] ?? [] as $message) {
                $this->addStepMessage($step->step_id, $message);
            }

            // Variables del paso
            foreach ($stepData['variables'] ?? [] as $variable) {
                $this->addStepVariable($step->step_id, $variable);
            }

            return $step;
        });
    }

    /**
     * Agrega un trigger al flujo.
     */
    public function addTrigger(string $flowId, array $triggerData): FlowTrigger
    {
        $validType = in_array($triggerData['type'], self::ALLOWED_TRIGGER_TYPES)
            ? $triggerData['type']
            : 'keyword';

        return FlowTrigger::create([
            'flow_id' => $flowId,
            'type' => $validType,
            'value' => $triggerData['value'],
            'priority' => (int)($triggerData['priority'] ?? 0)
        ]);
    }

    /**
     * Agrega trigger a flujo existente
     */
    public function addFlowTrigger(
        string $flowId, 
        string $type, 
        string $value, 
        int $priority = 0
    ): FlowTrigger {
        return $this->addTrigger($flowId, [
            'type' => $type,
            'value' => $value,
            'priority' => $priority
        ]);
    }

    /**
     * Crea paso básico y devuelve para edición incremental
     */
    public function createStepShell(
        string $flowId,
        string $type = 'message_sequence',
        int $order = null
    ): FlowStep {
        return $this->addStep($flowId, [
            'type' => $type,
            'order' => $order ?? $this->getNextStepOrder($flowId)
        ]);
    }

    /**
     * Agrega mensaje a un paso existente
     */
    public function addMessageToStep(
        string $stepId,
        string $type,
        string $content,
        int $order = 1,
        int $delay = 0
    ): StepMessage {
        return $this->addStepMessage($stepId, [
            'type' => $type,
            'content' => $content,
            'order' => $order,
            'delay' => $delay
        ]);
    }

    private function getNextStepOrder(string $flowId): int
    {
        return FlowStep::where('flow_id', $flowId)->max('order') + 1;
    }

    /**
     * Agrega variable de flujo
     */
    public function addFlowVariable(string $flowId, array $variableData): FlowVariable
    {
        return FlowVariable::create([
            'flow_id' => $flowId,
            'name' => $this->sanitizeVariableName($variableData['name']),
            'type' => $this->validateVariableType($variableData['type'] ?? 'string'),
            'default_value' => $variableData['default_value'] ?? null
        ]);
    }

    /** Métodos de validación */
    
    private function validateStepType(string $type): string
    {
        return in_array($type, self::ALLOWED_STEP_TYPES) 
            ? $type 
            : 'message_sequence';
    }

    private function validateFailureAction(string $action): string
    {
        $allowed = ['repeat', 'redirect', 'end_flow', 'transfer'];
        return in_array($action, $allowed) ? $action : 'end_flow';
    }

    private function parseValidationRules(array $rules): array
    {
        return [
            'required' => (bool)($rules['required'] ?? false),
            'type' => $this->validateVariableType($rules['type'] ?? 'string'),
            'regex' => $rules['regex'] ?? null,
            'min' => (int)($rules['min'] ?? 0),
            'max' => (int)($rules['max'] ?? 0)
        ];
    }

    

    /**
     * Agrega mensaje a un paso
     */
    public function addStepMessage(string $stepId, array $messageData): StepMessage
    {
        return StepMessage::create([
            'flow_step_id' => $stepId,
            'message_type' => $messageData['type'],
            'content' => $messageData['content'],
            'media_file_id' => $messageData['media_id'] ?? null,
            'delay_seconds' => (int)($messageData['delay'] ?? 0),
            'order' => (int)($messageData['order'] ?? 0)
        ]);
    }

    

    /**
     * Agrega o actualiza una variable del flujo.
     */
    public function setVariable(string $flowId, array $variableData): FlowVariable
    {
        return FlowVariable::updateOrCreate(
            ['flow_id' => $flowId, 'name' => $variableData['name']],
            [
                'type'          => Arr::get($variableData, 'type', 'string'),
                'default_value' => Arr::get($variableData, 'default_value'),
            ]
        );
    }

    /**
     * Clona un flujo completo (y todo lo asociado).
     */
    public function cloneFlow(string $flowId, array $overrides = []): Flow
    {
        return DB::transaction(function () use ($flowId, $overrides) {
            $original = Flow::with(['steps','triggers','variables','bots'])
                          ->findOrFail($flowId);

            $copy = $original->replicate(['flow_id']);
            $copy->fill($overrides)->save();

            // Mapeo de IDs antiguos a nuevos
            $stepMappings = [];
            
            foreach ($original->steps as $originalStep) {
                $newStep = $originalStep->replicate(['step_id']);
                $newStep->flow_id = $copy->flow_id;
                $newStep->save();
                $stepMappings[$originalStep->step_id] = $newStep->step_id;
            }

            // Actualizar referencias
            foreach ($copy->steps as $newStep) {
                if ($newStep->next_step_id && isset($stepMappings[$newStep->next_step_id])) {
                    $newStep->next_step_id = $stepMappings[$newStep->next_step_id];
                    $newStep->save();
                }
            }

            return $copy->load('steps');
        });
    }

    /**
     * Asocia un flujo a un bot (pivot).
     */
    public function attachFlowToBot(string $flowId, string $botId): void
    {
        Flow::findOrFail($flowId)
            ->bots()
            ->syncWithoutDetaching([$botId]);
    }

    /**
     * Desasocia un flujo de un bot.
     */
    public function detachFlowFromBot(string $flowId, string $botId): void
    {
        Flow::findOrFail($flowId)
            ->bots()
            ->detach([$botId]);
    }

    /**
     * Validación de contenido por tipo de paso
     */
    private function validateStepContent(string $type, array $content): array
    {
        $validators = [
            'menu' => fn($c) => isset($c['options']) && is_array($c['options']),
            'input' => fn($c) => isset($c['field_name']),
            'message' => fn($c) => isset($c['text']),
        ];

        if (isset($validators[$type]) && !$validators[$type]($content)) {
            throw new \InvalidArgumentException("Invalid content for step type: $type");
        }

        return $content;
    }

    /**
     * Agrega variable a un paso
     */
    public function addStepVariable(string $stepId, array $variableData): StepVariable
    {
        if (empty($variableData['name'])) {
            throw new \InvalidArgumentException("El nombre de la variable es requerido");
        }

        return StepVariable::create([
            'flow_step_id' => $stepId,
            'name' => $this->sanitizeVariableName($variableData['name']),
            'type' => $this->validateVariableType($variableData['type'] ?? 'string'),
            'validation_regex' => $variableData['regex'] ?? null,
            'is_required' => (bool)($variableData['required'] ?? false),
            'error_message' => $variableData['error_message'] ?? 'Validación fallida'
        ]);
    }

    /**
     * Sanitiza nombres de variables
     */
    private function sanitizeVariableName(string $name): string
    {
        // 1. Convertir a minúsculas
        // 2. Reemplazar espacios y guiones por underscores
        // 3. Eliminar caracteres no permitidos
        // 4. Limitar longitud a 64 caracteres
        
        return substr(
            preg_replace(
                '/[^a-z0-9_]/',
                '',
                str_replace([' ', '-'], '_', strtolower($name))
            ),
            0,
            64
        );
    }

    /**
     * Valida tipos de variables
     */
    private function validateVariableType(string $type): string
    {
        $allowedTypes = [
            'string', 'number', 'boolean', 
            'datetime', 'email', 'phone', 'custom_regex'
        ];
        
        return in_array(strtolower($type), $allowedTypes, true) 
            ? strtolower($type)
            : 'string';
    }

    /**
     * Valida nombre del flujo
     */
    private function validateName(string $name): string
    {
        $name = trim($name);
        
        if (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            throw new \InvalidArgumentException(
                "El nombre del flujo debe tener entre 3 y 255 caracteres"
            );
        }
        
        return $name;
    }

    /**
     * Valida tipo de flujo
     */
    private function validateFlowType(string $type): string
    {
        $allowedTypes = ['inbound', 'outbound', 'hybrid'];
        return in_array(strtolower($type), $allowedTypes, true)
            ? strtolower($type)
            : 'inbound';
    }

    /**
     * Normaliza palabras clave para triggers
     */
    private function normalizeKeywords(array $keywords, bool $isCaseSensitive = false): array
    {
        return collect($keywords)
            ->filter()
            ->map(function ($keyword) use ($isCaseSensitive) {
                return $isCaseSensitive 
                    ? trim($keyword)
                    : mb_strtolower(trim($keyword));
            })
            ->unique()
            ->values()
            ->toArray();
    }

    // public function executeFlow(string $flowId, string $contactId, string $sessionId = null) {
    //     $flow = Flow::with('steps')->findOrFail($flowId);
    //     $session = $this->getOrCreateSession($sessionId, $contactId, $flowId);
        
    //     $nextStep = $flow->steps()->where('order', $session->current_step)->first();
        
    //     if (!$nextStep) {
    //         $nextStep = $flow->initialStep;
    //         $session->update(['current_step' => $nextStep->order]);
    //     }
        
    //     $this->sendStepMessage($nextStep, $contactId, $session);
        
    //     return $session;
    // }
}
