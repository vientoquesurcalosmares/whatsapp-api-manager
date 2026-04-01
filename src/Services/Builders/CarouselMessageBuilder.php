<?php
namespace ScriptDevelop\WhatsappManager\Services\Builders;

class CarouselMessageBuilder
{
    private array $cards = [];
    private int $currentIndex = 0;

    /**
     * Agrega dinámicamente una tarjeta parametrizada al carrusel en tiempo de envío.
     *
     * @param callable $callback Recibe un objeto CarouselCardMessageBuilder.
     * @return self
     */
    public function addCard(callable $callback): self
    {
        $cardBuilder = new CarouselCardMessageBuilder($this->currentIndex);
        $callback($cardBuilder);

        $this->cards[] = $cardBuilder->toArray();
        $this->currentIndex++;

        return $this;
    }

    public function toArray(): array
    {
        return $this->cards;
    }
}

class CarouselCardMessageBuilder
{
    private int $cardIndex;
    private array $components = [];

    public function __construct(int $index)
    {
        $this->cardIndex = $index;
    }

    /**
     * Inyecta dependencias para la imagen/video/producto del encabezado de la tarjeta.
     */
    public function addHeader(string $format, array $parameters): self
    {
        $this->components[] = [
            'type' => 'header',
            'parameters' => [
                [
                    'type' => strtolower($format),
                    strtolower($format) => $parameters
                ]
            ]
        ];
        return $this;
    }

    /**
     * Inyecta variables dinámicas al cuerpo de la tarjeta.
     */
    public function addBody(array $variables): self
    {
        $parameters = array_map(function ($text) {
            return ['type' => 'text', 'text' => $text];
        }, $variables);

        $this->components[] = [
            'type' => 'body',
            'parameters' => $parameters
        ];
        return $this;
    }

    /**
     * Inyecta variables dinámicas a un botón específico de la tarjeta (Ej. URL dinámica, Payload de quick reply o Código de cupón).
     */
    public function addButton(string $subType, int $index, array $parameters): self
    {
        $this->components[] = [
            'type' => 'button',
            'sub_type' => strtolower($subType),
            'index' => (string)$index, // Meta requiere en algunas APIs String o Int, mejor forzar string para indices de botones
            'parameters' => $parameters
        ];
        return $this;
    }

    public function toArray(): array
    {
        return [
            'card_index' => $this->cardIndex,
            'components' => $this->components
        ];
    }
}
