<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Enums\MessageStatus;
use ScriptDevelop\WhatsappManager\Models\Contact;
use ScriptDevelop\WhatsappManager\Models\Message;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Exceptions\WhatsappApiException;

class MessageDispatcherService
{
    public function __construct(
        protected ApiClient $apiClient
    ) {}

    public function sendTextMessage(
        string $phoneNumberId,
        string $to,
        string $text,
        bool $previewUrl = false
    ): Message {
        $phoneNumber = $this->validatePhoneNumber($phoneNumberId);
        $contact = $this->resolveContact($to);

        $message = Message::create([
            'whatsapp_phone_id' => $phoneNumber->phone_number_id,
            'contact_id' => $contact->contact_id,
            'message_from' => $phoneNumber->display_phone_number,
            'message_to' => $to,
            'message_type' => 'text',
            'message_content' => $text,
            'status' => MessageStatus::PENDING
        ]);

        try {
            $response = $this->sendViaApi($phoneNumber, $to, $text, $previewUrl);
            return $this->handleSuccess($message, $response);
        } catch (WhatsappApiException $e) {
            return $this->handleError($message, $e);
        }
    }

    private function validatePhoneNumber(string $phoneNumberId): WhatsappPhoneNumber
    {
        $phone = WhatsappPhoneNumber::with('businessAccount')
            ->findOrFail($phoneNumberId);

        if (!$phone->businessAccount?->api_token) {
            throw new \InvalidArgumentException('El número no tiene un token API válido asociado');
        }

        return $phone;
    }

    private function resolveContact(string $phoneNumber): Contact
    {
        return Contact::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['country_code' => substr($phoneNumber, 0, 3)]
        );
    }

    private function sendViaApi(
        WhatsappPhoneNumber $phone,
        string $to,
        string $text,
        bool $previewUrl
    ): array {
        return $this->apiClient->request(
            'POST',
            Endpoints::build(Endpoints::SEND_MESSAGE, [
                'phone_number_id' => $phone->api_phone_number_id
            ]),
            data: [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => $previewUrl,
                    'body' => $text
                ]
            ],
            headers: [
                'Authorization' => 'Bearer ' . $phone->businessAccount->api_token,
                'Content-Type' => 'application/json'
            ]
        );
    }

    private function handleSuccess(Message $message, array $response): Message
    {
        $message->update([
            'wa_id' => $response['messages'][0]['id'],
            'status' => MessageStatus::SENT,
            'json_content' => $response
        ]);

        return $message;
    }

    private function handleError(Message $message, WhatsappApiException $e): Message
    {
        $message->update([
            'status' => MessageStatus::FAILED,
            'code_error' => $e->getCode(),
            'title_error' => $e->getMessage(),
            'details_error' => json_encode($e->getDetails()),
            'json_content' => $e->getDetails()
        ]);

        return $message;
    }
}