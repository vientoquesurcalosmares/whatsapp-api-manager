<?php
namespace ScriptDevelop\WhatsappManager\Services\Builders;

class CarouselTemplateBuilder
{
    private array $cards = [];

    /**
     * Agrega la estructura genérica (base) de una tarjeta para crear la plantilla.
     */
    public function addCard(callable $callback): self
    {
        $cardBuilder = new CarouselCardTemplateBuilder();
        $callback($cardBuilder);

        $this->cards[] = [
            'components' => $cardBuilder->toArray()
        ];

        return $this;
    }

    public function toArray(): array
    {
        return $this->cards;
    }
}

class CarouselCardTemplateBuilder
{
    private array $components = [];
    private array $buttons = [];

    public function addHeader(string $format, array $exampleHandle = []): self
    {
        $component = [
            'type' => 'HEADER',
            'format' => strtoupper($format) // IMAGE, VIDEO o PRODUCT
        ];
        if (!empty($exampleHandle)) {
            $component['example'] = ['header_handle' => $exampleHandle];
        }
        $this->components[] = $component;
        return $this;
    }

    public function addBody(string $text, array $exampleStrings = []): self
    {
        $component = [
            'type' => 'BODY',
            'text' => $text
        ];
        if (!empty($exampleStrings)) {
            $component['example'] = ['body_text' => [$exampleStrings]];
        }
        $this->components[] = $component;
        return $this;
    }

    public function addQuickReplyButton(string $text): self
    {
        $this->buttons[] = [
            'type' => 'QUICK_REPLY',
            'text' => $text
        ];
        return $this;
    }

    public function addUrlButton(string $text, string $url, array $example = []): self
    {
        $btn = [
            'type' => 'URL',
            'text' => $text,
            'url' => $url
        ];
        if (!empty($example)) {
            $btn['example'] = $example;
        }
        $this->buttons[] = $btn;
        return $this;
    }

    public function addSpmButton(string $text = "View"): self
    {
         $this->buttons[] = [
            'type' => 'SPM',
            'text' => $text
        ];
        return $this;
    }

    public function addCallButton(string $text, string $phoneNumber): self
    {
        $this->buttons[] = [
            'type' => 'PHONE_NUMBER',
            'text' => $text,
            'phone_number' => $phoneNumber
        ];
        return $this;
    }

    public function toArray(): array
    {
        $components = $this->components;
        if (!empty($this->buttons)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $this->buttons
            ];
        }
        return $components;
    }
}
