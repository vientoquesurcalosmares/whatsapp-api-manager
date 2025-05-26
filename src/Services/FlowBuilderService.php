<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Models\{
    Flow,
    FlowTrigger,
    KeywordTrigger,
    RegexTrigger,
    TemplateTrigger,
    Template
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Arr;

class FlowBuilderService
{
    private $flow;
    private $triggers = [];
    private $allowedTriggerTypes = ['keyword', 'regex', 'template'];

    /**
     * Inicia la creación de un nuevo flujo
     */
    public function createFlow(array $data): self
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:inbound,outbound,hybrid',
            'trigger_mode' => 'required|in:any,all',
            'is_default' => 'sometimes|boolean',
            'entry_point_id' => 'nullable|ulid'
        ])->validate();

        $this->flow = Flow::create(array_merge($validated, [
            'is_active' => false // Inactivo hasta agregar triggers
        ]));

        return $this;
    }

    /**
     * Agrega un trigger de keywords
     */
    public function addKeywordTrigger(array|string $keywords, bool $caseSensitive = false, string $matchType = 'exact'): self
    {
        $this->validateTriggerType('keyword');
        
        $validated = Validator::make([
            'keywords' => Arr::wrap($keywords),
            'case_sensitive' => $caseSensitive,
            'match_type' => $matchType
        ], [
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:100',
            'case_sensitive' => 'boolean',
            'match_type' => 'in:exact,contains,starts_with,ends_with'
        ])->validate();

        $this->triggers[] = [
            'type' => 'keyword',
            'data' => $validated
        ];

        return $this;
    }

    /**
     * Agrega un trigger regex
     */
    public function addRegexTrigger(string $pattern, bool $matchFull = true, ?string $flags = null): self
    {
        $this->validateTriggerType('regex');
        
        $this->validateRegex($pattern);

        $this->triggers[] = [
            'type' => 'regex',
            'data' => [
                'pattern' => $pattern,
                'match_full' => $matchFull,
                'flags' => $flags
            ]
        ];

        return $this;
    }

    /**
     * Agrega un trigger de template
     */
    public function addTemplateTrigger(string $templateName, string $language = 'en', array $variables = []): self
    {
        $this->validateTriggerType('template');
        
        $this->validateTemplate($templateName);

        $this->triggers[] = [
            'type' => 'template',
            'data' => [
                'template_name' => $templateName,
                'language' => $language,
                'variables' => $variables
            ]
        ];

        return $this;
    }

    /**
     * Finaliza la creación y guarda todo
     */
    public function build(): Flow
    {
        return DB::transaction(function () {
            $this->processTriggers();
            $this->updateFlowStatus();
            
            return $this->flow->refresh()->load('triggers');
        });
    }

    private function processTriggers(): void
    {
        foreach ($this->triggers as $trigger) {
            $this->createTrigger($trigger);
        }
    }

    private function createTrigger(array $triggerData): void
    {
        $method = 'create' . ucfirst($triggerData['type']) . 'Trigger';
        $this->{$method}($triggerData['data']);
    }

    private function createKeywordTrigger(array $data): void
    {
        $keywordTrigger = KeywordTrigger::create([
            'keywords' => $data['keywords'],
            'case_sensitive' => $data['case_sensitive'],
            'match_type' => $data['match_type']
        ]);

        $this->createTriggerRelation($keywordTrigger, 'keyword');
    }

    private function createRegexTrigger(array $data): void
    {
        $regexTrigger = RegexTrigger::create([
            'pattern' => $data['pattern'],
            'match_full' => $data['match_full'],
            'flags' => $data['flags']
        ]);

        $this->createTriggerRelation($regexTrigger, 'regex');
    }

    private function createTemplateTrigger(array $data): void
    {
        $templateTrigger = TemplateTrigger::create([
            'template_name' => $data['template_name'],
            'language' => $data['language'],
            'variables' => $data['variables']
        ]);

        $this->createTriggerRelation($templateTrigger, 'template');
    }

    private function createTriggerRelation(Model $trigger, string $type): void
    {
        FlowTrigger::create([
            'flow_id' => $this->flow->flow_id,
            'type' => $type,
            'triggerable_id' => $trigger->getKey(),
            'triggerable_type' => $trigger->getMorphClass()
        ]);
    }

    private function validateTriggerType(string $type): void
    {
        if ($this->hasTriggerType($type)) {
            throw new \InvalidArgumentException("Ya existe un trigger de tipo $type para este flujo");
        }
    }

    private function hasTriggerType(string $type): bool
    {
        return in_array($type, array_column($this->triggers, 'type'));
    }

    private function validateRegex(string $pattern): void
    {
        if (@preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException("Expresión regular inválida: $pattern");
        }
    }

    private function validateTemplate(string $templateName): void
    {
        if (!Template::where('name', $templateName)->exists()) {
            throw new \InvalidArgumentException("La plantilla $templateName no existe");
        }
    }

    private function updateFlowStatus(): void
    {
        $this->flow->update([
            'is_active' => !empty($this->triggers)
        ]);
    }

    /**
     * Helper para crear flujo rápido con keywords
     */
    public static function createWithKeywords(
        string $name,
        array $keywords,
        string $type = 'inbound',
        string $matchType = 'exact',
        bool $caseSensitive = false
    ): Flow {
        return (new self())
            ->createFlow([
                'name' => $name,
                'type' => $type,
                'trigger_mode' => 'any'
            ])
            ->addKeywordTrigger($keywords, $caseSensitive, $matchType)
            ->build();
    }
}