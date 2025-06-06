<?php

namespace ScriptDevelop\WhatsappManager\Services;

class ScreenBuilder
{
    protected FlowBuilder $parent;
    protected array $screenData = [];
    protected array $elements = [];
    protected ?ElementBuilder $currentElement = null;

    public function __construct(FlowBuilder $parent, string $name)
    {
        $this->parent = $parent;
        $this->screenData['name'] = $name;
    }

    public function title(string $title): self
    {
        $this->screenData['title'] = $title;
        return $this;
    }

    public function content(string $content): self
    {
        $this->screenData['content'] = $content;
        return $this;
    }

    public function isStart(bool $isStart): self
    {
        $this->screenData['is_start'] = $isStart;
        return $this;
    }

    public function order(int $order): self
    {
        $this->screenData['order'] = $order;
        return $this;
    }

    /**
     * Comienza a construir un nuevo elemento
     */
    public function element(string $name): ElementBuilder
    {
        $this->currentElement = new ElementBuilder($this, $name);
        return $this->currentElement;
    }

    /**
     * Agrega un elemento construido directamente
     */
    public function addElement(array $elementData): self
    {
        $this->elements[] = $elementData;
        return $this;
    }

    /**
     * Finaliza la construcción de la pantalla y retorna al FlowBuilder
     */
    public function endScreen(): FlowBuilder
    {
        return $this->parent;
    }

    /**
     * Construye la estructura final de la pantalla
     */
    public function build(): array
    {
        // Si hay un elemento en construcción, lo agregamos
        if ($this->currentElement) {
            $this->elements[] = $this->currentElement->build();
            $this->currentElement = null;
        }

        $this->screenData['elements'] = $this->elements;
        return $this->screenData;
    }
}