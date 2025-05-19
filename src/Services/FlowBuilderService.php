<?php
namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\Flow;
use ScriptDevelop\WhatsappManager\Models\FlowStep;
use ScriptDevelop\WhatsappManager\Models\FlowTrigger;
use ScriptDevelop\WhatsappManager\Models\FlowVariable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class FlowBuilderService
{
    /**
     * Crea un nuevo flujo con sus triggers iniciales.
     */
    public function createFlow(array $data): Flow
    {
        return DB::transaction(function () use ($data) {
            $flow = Flow::create([
                'name'              => Arr::get($data, 'name'),
                'description'       => Arr::get($data, 'description'),
                'trigger_keywords'  => Arr::get($data, 'trigger_keywords', []),
                'is_case_sensitive' => Arr::get($data, 'is_case_sensitive', false),
                'is_default'        => Arr::get($data, 'is_default', false),
                'is_active'         => Arr::get($data, 'is_active', true),
            ]);

            // Si vienen triggers detallados:
            foreach (Arr::get($data, 'triggers', []) as $t) {
                $this->addTrigger($flow->flow_id, $t);
            }
            
            // Creación de pasos iniciales
            if (isset($data['initial_steps'])) {
                foreach ($data['initial_steps'] as $step) {
                    $this->addStep($flow->flow_id, $step);
                }
            }
            
            return $flow;
        });
    }

    /**
     * Agrega un paso al flujo.
     */
    public function addStep(string $flowId, array $stepData): FlowStep
    {
        $maxOrder = FlowStep::where('flow_id', $flowId)->max('order') ?? 0;
        
        return FlowStep::create([
            'flow_id'       => $flowId,
            'order'         => $stepData['order'] ?? $maxOrder + 1,
            'type'          => Arr::get($stepData, 'type'),
            'content'       => $this->validateStepContent(
                Arr::get($stepData, 'type'), 
                Arr::get($stepData, 'content')
            ),
            'next_step_id'  => Arr::get($stepData, 'next_step_id'),
            'is_terminal'   => Arr::get($stepData, 'is_terminal', false),
        ]);
    }

    /**
     * Agrega un trigger al flujo.
     */
    public function addTrigger(string $flowId, array $triggerData): FlowTrigger
    {
        return FlowTrigger::create([
            'flow_id' => $flowId,
            'type'    => Arr::get($triggerData, 'type'),
            'value'   => Arr::get($triggerData, 'value'),
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
