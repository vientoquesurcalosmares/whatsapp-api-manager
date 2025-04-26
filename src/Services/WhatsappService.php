<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Illuminate\Support\Facades\Log;
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














    public function getBusinessAccount(string $whatsappBusinessId): array
    {
        $response = $this->apiClient->request(
            'GET',
            Endpoints::GET_BUSINESS_ACCOUNT,
            ['whatsapp_business_id' => $whatsappBusinessId],
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de getBusinessAccount:', $response);
        return $response;
    }

    public function getPhoneNumbers(string $whatsappBusinessId): array
    {
        $response = $this->apiClient->request(
            'GET',
            Endpoints::GET_PHONE_NUMBERS,
            ['whatsapp_business_id' => $whatsappBusinessId],
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de getPhoneNumbers API:', $response);
        return $response['data'] ?? $response;
    }

    /**
     * Obtiene el perfil de whatsapp business.
     */
    public function getBusinessProfile(string $phoneNumberId): array
    {
        $response = $this->apiClient->request(
            'GET',
            Endpoints::GET_BUSINESS_PROFILE,
            ['phone_number_id' => $phoneNumberId],
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de getBusinessProfile API:', $response);
        return $response;
    }

    public function withTempToken(string $token): self
    {
        $this->businessAccount = new WhatsappBusinessAccount([
            'api_token' => $token
        ]);
        return $this;
    }

}