<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;


class WhatsappService
{
    protected ?WhatsappBusinessAccount $businessAccount = null;

    public function __construct(
        protected ApiClient $apiClient,
        protected WhatsappBusinessAccountRepository $accountRepo
    ) {}

    /**
     * Establece la cuenta empresarial a usar.
     */
    public function forAccount(string $accountId): self
    {
        $this->businessAccount = $this->accountRepo->find($accountId);
        return $this;
    }

    /**
     * Obtiene el perfil de whatsapp business.
     */
    public function getBusinessProfile(): array
    {
        $this->ensureAccountIsSet();

        return $this->apiClient->request(
            'GET',
            Endpoints::GET_BUSINESS_PROFILE,
            ['phone_number_id' => $this->businessAccount->phone_number_id],
            headers: $this->getAuthHeaders()
        );
    }

    /**
     * Envía un mensaje de texto.
     */
    public function sendTextMessage(string $to, string $message): array
    {
        $this->ensureAccountIsSet();

        return $this->apiClient->request(
            'POST',
            Endpoints::SEND_MESSAGE,
            ['phone_number_id' => $this->businessAccount->phone_number_id],
            data: [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $message]
            ],
            headers: $this->getAuthHeaders()
        );
    }

    /**
     * Asegura que una cuenta esté configurada.
     */
    protected function ensureAccountIsSet(): void
    {
        if (!$this->businessAccount) {
            throw new \RuntimeException('Debes establecer una cuenta primero usando forAccount()');
        }
    }

    /**
     * Headers de autenticación.
     */
    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->businessAccount->api_token
        ];
    }
}