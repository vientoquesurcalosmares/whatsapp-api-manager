<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Illuminate\Database\Eloquent\Model;

class CatalogProductBuilder
{
    private MessageDispatcherService $dispatcher;
    private string $phoneNumberId;
    private string $countryCode;
    private string $phoneNumber;
    private array $sections = [];
    private ?string $currentSection = null;
    private ?string $body = null;
    private ?string $header = null;
    private ?string $footer = null;
    private ?string $contextMessageId = null;

    public function __construct(MessageDispatcherService $dispatcher, string $phoneNumberId)
    {
        $this->dispatcher = $dispatcher;
        $this->phoneNumberId = $phoneNumberId;
    }

    public function to(string $countryCode, string $phoneNumber): self
    {
        $this->countryCode = $countryCode;
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function withBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function withHeader(?string $header): self
    {
        $this->header = $header;
        return $this;
    }

    public function withFooter(?string $footer): self
    {
        $this->footer = $footer;
        return $this;
    }

    public function startSection(string $title): self
    {
        if ($this->currentSection) {
            throw new \LogicException('Debes cerrar la sección actual antes de iniciar otra');
        }
        
        $this->currentSection = $title;
        $this->sections[$title] = ['title' => $title, 'product_items' => []];
        return $this;
    }

    public function addProduct(string $productId): self
    {
        if (!$this->currentSection) {
            throw new \LogicException('Debes iniciar una sección primero con startSection()');
        }

        $this->sections[$this->currentSection]['product_items'][] = [
            'product_retailer_id' => $productId
        ];

        return $this;
    }

    public function endSection(): self
    {
        if (!$this->currentSection) {
            throw new \LogicException('No hay sección activa para cerrar');
        }
        
        $this->currentSection = null;
        return $this;
    }

    public function inReplyTo(?string $contextMessageId): self
    {
        $this->contextMessageId = $contextMessageId;
        return $this;
    }

    public function send(): Model
    {
        if ($this->currentSection) {
            throw new \LogicException('Tienes una sección abierta sin cerrar');
        }
        
        return $this->dispatcher->sendMultiProductMessage(
            $this->phoneNumberId,
            $this->countryCode,
            $this->phoneNumber,
            array_values($this->sections),
            $this->body,
            $this->header,
            $this->footer,
            $this->contextMessageId
        );
    }
}