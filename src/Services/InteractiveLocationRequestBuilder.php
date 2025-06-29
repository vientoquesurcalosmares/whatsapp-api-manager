<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Services\MessageDispatcherService;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;
use Illuminate\Database\Eloquent\Model;

class InteractiveLocationRequestBuilder
{
    private MessageDispatcherService $dispatcher;
    private string $phoneNumberId;
    private string $countryCode;
    private string $phoneNumber;
    private string $body;
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


    public function inReplyTo(?string $contextMessageId): self
    {
        $this->contextMessageId = $contextMessageId;
        return $this;
    }

    public function send(): Model
    {
        return $this->dispatcher->sendLocationRequestMessage(
            $this->phoneNumberId,
            $this->countryCode,
            $this->phoneNumber,
            $this->body,
            $this->contextMessageId
        );
    }
}