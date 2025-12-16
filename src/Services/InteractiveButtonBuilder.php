<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;
use Illuminate\Database\Eloquent\Model;

class InteractiveButtonBuilder
{
    private MessageDispatcherService $dispatcher;
    private string $phoneNumberId;
    private string $countryCode;
    private string $phoneNumber;
    private $header = null;
    private string $body;
    private array $buttons = [];
    private ?string $footer = null;
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

    public function withHeader($header): self
    {
        $this->header = $header;
        return $this;
    }

    public function withBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function addButton(string $id, string $title): self
    {
        if (count($this->buttons) >= 3) {
            throw new \InvalidArgumentException('Maximum 3 buttons allowed');
        }
        
        $this->buttons[] = ['id' => $id, 'title' => $title];
        return $this;
    }

    public function withFooter(?string $footer): self
    {
        $this->footer = $footer;
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
        return $this->dispatcher->sendInteractiveButtonsMessage(
            $this->phoneNumberId,
            $this->countryCode,
            $this->phoneNumber,
            $this->body,
            $this->buttons,
            $this->footer,
            $this->header,
            $this->contextMessageId
        );
    }
}