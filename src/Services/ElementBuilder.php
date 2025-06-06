<?php

namespace ScriptDevelop\WhatsappManager\Services;

class ElementBuilder
{
    protected ScreenBuilder $parent;
    protected array $elementData = [];

    public function __construct(ScreenBuilder $parent, string $name)
    {
        $this->parent = $parent;
        $this->elementData['name'] = $name;
    }

    public function type(string $type): self
    {
        $this->elementData['type'] = $type;
        return $this;
    }

    public function label(string $label): self
    {
        $this->elementData['label'] = $label;
        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->elementData['placeholder'] = $placeholder;
        return $this;
    }

    public function required(bool $required): self
    {
        $this->elementData['required'] = $required;
        return $this;
    }

    /**
     * Finaliza la construcción del elemento y retorna al ScreenBuilder
     */
    public function endElement(): ScreenBuilder
    {
        return $this->parent;
    }

    /**
     * Construye la estructura final del elemento
     */
    public function build(): array
    {
        return $this->elementData;
    }
}