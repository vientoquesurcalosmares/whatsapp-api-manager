<?php
namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\Flow;
use ScriptDevelop\WhatsappManager\Models\FlowStep;
use ScriptDevelop\WhatsappManager\Models\FlowTrigger;
use ScriptDevelop\WhatsappManager\Models\FlowVariable;
use Illuminate\Support\Arr;

class FlowBuilderService
{
    /**
     * Crea un nuevo flujo con sus triggers iniciales.
     */
    public function createFlow(array $data): Flow
    {
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

        return $flow;
    }

    /**
     * Agrega un paso al flujo.
     */
    public function addStep(string $flowId, array $stepData): FlowStep
    {
        $order = Arr::get($stepData, 'order', FlowStep::where('flow_id', $flowId)->max('order') + 1);

        return FlowStep::create([
            'flow_id'       => $flowId,
            'order'         => $order,
            'type'          => Arr::get($stepData, 'type'),
            'content'       => Arr::get($stepData, 'content'),
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
        $original = Flow::with(['steps','triggers','variables','bots'])->findOrFail($flowId);

        // Clonamos flujo
        $copy = $original->replicate(['flow_id']);
        $copy->fill($overrides);
        $copy->push(); // guarda y genera nuevo ULID

        // Clonamos triggers y variables
        foreach ($original->triggers as $t) {
            $copy->triggers()->create($t->replicate(['trigger_id'])->toArray());
        }
        foreach ($original->variables as $v) {
            $copy->variables()->create($v->replicate(['variable_id'])->toArray());
        }

        // Clonamos pasos (manteniendo orden y next_step_id más adelante)
        $oldToNew = [];
        foreach ($original->steps as $step) {
            $new = $copy->steps()->create($step->replicate(['step_id','next_step_id'])->toArray());
            $oldToNew[$step->step_id] = $new->step_id;
        }
        // Ajustamos referencias next_step_id
        foreach ($copy->steps as $step) {
            if ($originalStep = $original->steps->first(fn($s)=> $oldToNew[$s->step_id] === $step->step_id)) {
                $origNext = $originalStep->next_step_id;
                $step->next_step_id = $origNext ? $oldToNew[$origNext] : null;
                $step->save();
            }
        }

        // Reasociamos bots (pivot)
        foreach ($original->bots as $bot) {
            $copy->bots()->attach($bot->whatsapp_bot_id);
        }

        return $copy;
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
}
