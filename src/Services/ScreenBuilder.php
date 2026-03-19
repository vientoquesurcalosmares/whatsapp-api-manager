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

    // ==========================================
    // Propiedades Básicas
    // ==========================================
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

    // ==========================================
    // Nuevas Propiedades (Flows v3.0+)
    // ==========================================

    /**
     * Define el modelo de datos (JSON Schema) para esta pantalla.
     * Indispensable para variables dinámicas, listas desplegables, etc.
     */
    public function data(array $data): self
    {
        $this->screenData['data'] = $data;
        return $this;
    }

    /**
     * Indica si esta pantalla es el final del flujo (deshabilita navegación).
     */
    public function terminal(bool $terminal = true): self
    {
        $this->screenData['terminal'] = $terminal;
        return $this;
    }

    /**
     * Muestra la pantalla con un estilo de éxito (Checkmark animado).
     * Solo tiene efecto si terminal es true.
     */
    public function success(bool $success = true): self
    {
        $this->screenData['success'] = $success;
        return $this;
    }

    // ==========================================
    // Gestión de Elementos
    // ==========================================

    /**
     * Comienza a construir un nuevo elemento
     */
    public function element(string $name): ElementBuilder
    {
        // Auto-guardado: Si había un elemento en construcción y el desarrollador 
        // olvidó llamar a endElement(), lo guardamos antes de iniciar uno nuevo.
        if ($this->currentElement) {
            $this->elements[] = $this->currentElement->build();
        }

        $this->currentElement = new ElementBuilder($this, $name);
        return $this->currentElement;
    }

    /**
     * Agrega un elemento construido directamente (arreglo crudo)
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
        // Si hay un elemento en construcción al final, lo agregamos
        if ($this->currentElement) {
            $this->elements[] = $this->currentElement->build();
            $this->currentElement = null; // Limpiar
        }

        $this->screenData['elements'] = $this->elements;
        return $this->screenData;
    }
}