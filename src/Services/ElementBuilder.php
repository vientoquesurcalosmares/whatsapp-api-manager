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

    // ==========================================
    // Propiedades Básicas y Comunes
    // ==========================================
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
    public function text(string|array $text): self
    {
        $this->elementData['text'] = $text;
        return $this;
    }
    public function placeholder(string $placeholder): self
    {
        $this->elementData['placeholder'] = $placeholder;
        return $this;
    }
    public function required(bool|string $required): self
    {
        $this->elementData['required'] = $required;
        return $this;
    }
    public function visible(bool|string $visible): self
    {
        $this->elementData['visible'] = $visible;
        return $this;
    }
    public function enabled(bool|string $enabled): self
    {
        $this->elementData['enabled'] = $enabled;
        return $this;
    }
    public function description(string $description): self
    {
        $this->elementData['description'] = $description;
        return $this;
    }

    // ==========================================
    // Propiedades de Texto y RichText
    // ==========================================
    public function markdown(bool $markdown): self
    {
        $this->elementData['markdown'] = $markdown;
        return $this;
    }
    public function fontWeight(string $weight): self
    {
        $this->elementData['font-weight'] = $weight;
        return $this;
    }
    public function strikethrough(bool|string $strikethrough): self
    {
        $this->elementData['strikethrough'] = $strikethrough;
        return $this;
    }

    // ==========================================
    // Propiedades de Entradas (Inputs / TextAreas)
    // ==========================================
    public function inputType(string $type): self
    {
        $this->elementData['input-type'] = $type;
        return $this;
    }
    public function helperText(string $text): self
    {
        $this->elementData['helper-text'] = $text;
        return $this;
    }
    public function errorMessage(string $text): self
    {
        $this->elementData['error-message'] = $text;
        return $this;
    }
    public function minChars(int|string $min): self
    {
        $this->elementData['min-chars'] = $min;
        return $this;
    }
    public function maxChars(int|string $max): self
    {
        $this->elementData['max-chars'] = $max;
        return $this;
    }
    public function maxLength(int|string $max): self
    {
        $this->elementData['max-length'] = $max;
        return $this;
    }
    public function pattern(string $pattern): self
    {
        $this->elementData['pattern'] = $pattern;
        return $this;
    }
    public function initValue(string|array $value): self
    {
        $this->elementData['init-value'] = $value;
        return $this;
    }

    // ==========================================
    // Propiedades de Selectores y Listas
    // ==========================================
    public function options(array $options): self
    {
        $this->elementData['options'] = $options;
        return $this;
    }
    public function dataSource(string|array $source): self
    {
        $this->elementData['data-source'] = $source;
        return $this;
    }
    public function minSelectedItems(int|string $min): self
    {
        $this->elementData['min-selected-items'] = $min;
        return $this;
    }
    public function maxSelectedItems(int|string $max): self
    {
        $this->elementData['max-selected-items'] = $max;
        return $this;
    }

    // ==========================================
    // Propiedades Multimedia (Images, Carousels)
    // ==========================================
    public function src(string $src): self
    {
        $this->elementData['src'] = $src;
        return $this;
    }
    public function altText(string $text): self
    {
        $this->elementData['alt-text'] = $text;
        return $this;
    }
    public function scaleType(string $type): self
    {
        $this->elementData['scale-type'] = $type;
        return $this;
    }
    public function aspectRatio(int|float|string $ratio): self
    {
        $this->elementData['aspect-ratio'] = $ratio;
        return $this;
    }
    public function images(string|array $images): self
    {
        $this->elementData['images'] = $images;
        return $this;
    }

    // ==========================================
    // Componentes Lógicos (If, Switch)
    // ==========================================
    public function condition(string $condition): self
    {
        $this->elementData['condition'] = $condition;
        return $this;
    }
    public function then(array $components): self
    {
        $this->elementData['then'] = $components;
        return $this;
    }
    public function else(array $components): self
    {
        $this->elementData['else'] = $components;
        return $this;
    }
    public function value(string $value): self
    {
        $this->elementData['value'] = $value;
        return $this;
    }
    public function cases(array $cases): self
    {
        $this->elementData['cases'] = $cases;
        return $this;
    }

    // ==========================================
    // Propiedades de Fechas (DatePicker, Calendar)
    // ==========================================
    public function mode(string $mode): self
    {
        $this->elementData['mode'] = $mode;
        return $this;
    }
    public function minDate(string $date): self
    {
        $this->elementData['min-date'] = $date;
        return $this;
    }
    public function maxDate(string $date): self
    {
        $this->elementData['max-date'] = $date;
        return $this;
    }
    public function unavailableDates(string|array $dates): self
    {
        $this->elementData['unavailable-dates'] = $dates;
        return $this;
    }
    public function includeDays(string|array $days): self
    {
        $this->elementData['include-days'] = $days;
        return $this;
    }

    // ==========================================
    // Propiedades Especiales (NavigationList, Footer)
    // ==========================================
    public function listItems(string|array $items): self
    {
        $this->elementData['list-items'] = $items;
        return $this;
    }
    public function leftCaption(string $caption): self
    {
        $this->elementData['left-caption'] = $caption;
        return $this;
    }
    public function centerCaption(string $caption): self
    {
        $this->elementData['center-caption'] = $caption;
        return $this;
    }
    public function rightCaption(string $caption): self
    {
        $this->elementData['right-caption'] = $caption;
        return $this;
    }

    // ==========================================
    // Acciones y Eventos
    // ==========================================
    public function action(string $actionName, array|object $payload = []): self
    {
        // Meta rechaza arreglos vacíos `[]` en el payload, exige objetos vacíos `{}`
        if (empty($payload)) {
            $payload = (object) [];
        }
        $this->elementData['action'] = [
            'name' => $actionName,
            'payload' => $payload
        ];
        return $this;
    }

    public function nextScreen(string $screenName): self
    {
        if (!isset($this->elementData['action'])) {
            $this->action('navigate'); // Asigna 'navigate' por defecto si no se especificó acción
        }
        $this->elementData['action']['next'] = [
            'type' => 'screen',
            'name' => $screenName
        ];
        return $this;
    }

    public function onSelectAction(string $actionName, array|object $payload = []): self
    {
        if (empty($payload)) {
            $payload = (object) [];
        }
        $this->elementData['on-select-action'] = [
            'name' => $actionName,
            'payload' => $payload
        ];
        return $this;
    }

    public function onUnselectAction(string $actionName, array|object $payload = []): self
    {
        if (empty($payload)) {
            $payload = (object) [];
        }
        $this->elementData['on-unselect-action'] = [
            'name' => $actionName,
            'payload' => $payload
        ];
        return $this;
    }

    // ==========================================
    // Finalizadores
    // ==========================================
    /**
     * Finaliza la construcción del elemento y retorna al ScreenBuilder
     */
    public function endElement(): ScreenBuilder
    {
        return $this->parent;
    }

    /**
     * Construye la estructura final del elemento (arreglo crudo)
     */
    public function build(): array
    {
        return $this->elementData;
    }

    // ==========================================
    // Atributos Genéricos / Dinámicos
    // ==========================================
    public function addAttributes(array $attributes): self
    {
        $this->elementData = array_merge($this->elementData, $attributes);
        return $this;
    }
}