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

    public function getPhoneNumberDetails(string $phoneNumberId): array
    {
        // Construir URL con versión y parámetros
        $url = Endpoints::build(
            Endpoints::GET_PHONE_DETAILS,
            [
                'version' => config('whatsapp-manager.api.version'), // Obtener versión del config
                'phone_number_id' => $phoneNumberId
            ]
        ) . '?fields=' . urlencode('verified_name,code_verification_status,display_phone_number,quality_rating,platform_type,throughput,webhook_configuration');

        Log::channel('whatsapp')->debug('URL de detalles de número:', ['url' => $url]);

        return $this->apiClient->request(
            'GET',
            $url,
            headers: $this->getAuthHeaders() // ✅ Incluir token de autenticación
        );
    }
    
    public function getBusinessProfile(string $phoneNumberId): array
    {
        // Construir URL con parámetros de campos
        $url = Endpoints::build(Endpoints::GET_BUSINESS_PROFILE, [
            'phone_number_id' => $phoneNumberId
        ]) . '?' . http_build_query([
            'fields' => 'about,address,description,email,profile_picture_url,websites,vertical'
        ]);

        Log::channel('whatsapp')->debug('URL de Solicitud de Perfil:', ['url' => $url]);

        $response = $this->apiClient->request(
            'GET',
            $url,
            headers: $this->getAuthHeaders()
        );

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