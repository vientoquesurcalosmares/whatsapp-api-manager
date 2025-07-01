<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;
use Illuminate\Database\Eloquent\Model;

class InteractiveCtaUrlBuilder
{
    private MessageDispatcherService $dispatcher;
    private string $phoneNumberId;
    private string $countryCode;
    private string $phoneNumber;
    private $header = null;
    private string $body;
    private string $buttonText;
    private string $url;
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

    public function withButton(string $buttonText, string $url): self
    {
        $this->buttonText = $buttonText;
        $this->url = $url;
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
     * Envía el mensaje CTA URL
     */
    public function send(): Model
    {
        return $this->dispatcher->sendCtaUrlMessage(
            $this->phoneNumberId,
            $this->countryCode,
            $this->phoneNumber,
            $this->body,
            $this->buttonText,
            $this->url,
            $this->header,
            $this->footer,
            $this->contextMessageId
        );
    }
}