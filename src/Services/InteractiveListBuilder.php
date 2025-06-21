<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;
use Illuminate\Database\Eloquent\Model;

class InteractiveListBuilder
{
    private MessageDispatcherService $dispatcher;
    private string $phoneNumberId;
    private string $countryCode;
    private string $phoneNumber;
    private string $body;
    private string $buttonText;
    private ?string $header = null;
    private ?string $footer = null;
    private array $sections = [];
    private ?string $currentSection = null;
    private ?string $contextMessageId = null;

    public function __construct(MessageDispatcherService $dispatcher, string $phoneNumberId)
    {
        $this->dispatcher = $dispatcher;
        $this->phoneNumberId = $phoneNumberId; // Añadir esta línea
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

    public function withButtonText(string $buttonText): self
    {
        $this->buttonText = $buttonText;
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
        $this->sections[$title] = [];
        return $this;
    }

    public function addRow(string $id, string $title, ?string $description = null): self
    {
        if (strlen($title) > 24) {
            throw new \InvalidArgumentException('Título máximo 24 caracteres');
        }
        
        if ($description && strlen($description) > 72) {
            throw new \InvalidArgumentException('Descripción máxima 72 caracteres');
        }
        
        if (!$this->currentSection) {
            throw new \LogicException('Debes iniciar una sección primero con startSection()');
        }

        $this->sections[$this->currentSection][] = [
            'id' => $id,
            'title' => $title,
            'description' => $description
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

    /**
     * Envía el mensaje construido
     * 
     * @return Model
     * @throws WhatsappApiException
     */
    public function send(): Model
    {
        if ($this->currentSection) {
            throw new \LogicException('Tienes una sección abierta sin cerrar');
        }
        
        // Convertir secciones al formato requerido
        $formattedSections = [];
        foreach ($this->sections as $title => $rows) {
            $formattedSections[] = [
                'title' => $title,
                'rows' => $rows
            ];
        }

        return $this->dispatcher->sendListMessage(
            $this->phoneNumberId,
            $this->countryCode,
            $this->phoneNumber,
            $this->buttonText,
            $formattedSections,
            $this->body,
            $this->header,
            $this->footer,
            $this->contextMessageId
        );
    }
}