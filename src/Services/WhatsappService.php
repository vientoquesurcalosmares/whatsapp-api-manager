<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
//use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use ScriptDevelop\WhatsappManager\Repositories\WhatsappBusinessAccountRepository;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

/**
 * Servicio para interactuar con la API de WhatsApp Business.
 * Proporciona métodos para gestionar cuentas empresariales, números de teléfono y perfiles.
 */
class WhatsappService
{
    /**
     * La cuenta empresarial de WhatsApp actualmente configurada.
     *
     * @var Model|null
     */
    protected ?Model $businessAccount = null;

    /**
     * Constructor de la clase.
     *
     * @param ApiClient $apiClient Cliente para realizar solicitudes a la API de WhatsApp.
     * @param WhatsappBusinessAccountRepository $accountRepo Repositorio para gestionar cuentas empresariales.
     */
    public function __construct(
        protected ApiClient $apiClient,
        protected WhatsappBusinessAccountRepository $accountRepo
    ) {}

    /**
     * Establece la cuenta empresarial a usar.
     *
     * @param string $accountId El ID de la cuenta empresarial.
     * @return self
     */
    public function forAccount(string $accountId): self
    {
        $this->businessAccount = $this->accountRepo->find($accountId);
        return $this;
    }

    /**
     * Asegura que una cuenta empresarial esté configurada.
     *
     * @return void
     * @throws \RuntimeException Si no se ha configurado una cuenta empresarial.
     */
    protected function ensureAccountIsSet(): void
    {
        if (!$this->businessAccount) {
            throw new \RuntimeException('Debes establecer una cuenta primero usando forAccount()');
        }
    }

    /**
     * Obtiene los headers de autenticación para las solicitudes a la API.
     *
     * @return array Los headers de autenticación.
     */
    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->businessAccount->api_token
        ];
    }

    /**
     * Obtiene información de una cuenta empresarial de WhatsApp.
     *
     * @param string $whatsappBusinessId El ID de la cuenta empresarial de WhatsApp.
     * @return array La respuesta de la API con los detalles de la cuenta empresarial.
     */
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

    /**
     * Obtiene las aplicaciones suscritas a una cuenta empresarial de WhatsApp.
     *
     * @param string $whatsappBusinessId El ID de la cuenta empresarial de WhatsApp.
     * @return array La respuesta de la API con las aplicaciones suscritas.
     */
    public function getBusinessAccountApp(string $whatsappBusinessId): array
    {
        $response = $this->apiClient->request(
            'GET',
            Endpoints::GET_BUSINESS_ACCOUNT_SUSCRIPTIONS,
            ['whatsapp_business_id' => $whatsappBusinessId],
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de getBusinessAccountaPP:', $response);
        return $response;
    }

    /**
     * Obtiene los números de teléfono asociados a una cuenta empresarial.
     *
     * @param string $whatsappBusinessId El ID de la cuenta empresarial de WhatsApp.
     * @return array La respuesta de la API con los números de teléfono.
     */
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

    public function getPhoneNumberNameStatus(string $phoneNumberId): array
    {
        $url = Endpoints::build(
            Endpoints::GET_PHONE_DETAILS,
            [
                'version' => config('whatsapp-manager.api.version'),
                'phone_number_id' => $phoneNumberId
            ]
        ) . '?fields=name_status';

        Log::channel('whatsapp')->debug('URL de estado del nombre:', ['url' => $url]);

        return $this->apiClient->request(
            'GET',
            $url,
            headers: $this->getAuthHeaders()
        );
    }

    /**
     * Obtiene los detalles de un número de teléfono específico.
     *
     * @param string $phoneNumberId El ID del número de teléfono.
     * @return array La respuesta de la API con los detalles del número de teléfono.
     */
    public function getPhoneNumberDetails(string $phoneNumberId): array
    {
        $fields = urlencode('verified_name,code_verification_status,display_phone_number,'
        . 'quality_rating,platform_type,throughput,webhook_configuration,'
        . 'is_official_business_account,is_pin_enabled,status');

        $url = Endpoints::build(
            Endpoints::GET_PHONE_DETAILS,
            [
                'version' => config('whatsapp-manager.api.version'),
                'phone_number_id' => $phoneNumberId
            ]
        ) . '?fields=' . $fields;

        Log::channel('whatsapp')->debug('URL de detalles de número:', ['url' => $url]);

        return $this->apiClient->request(
            'GET',
            $url,
            headers: $this->getAuthHeaders()
        );
    }

    /**
     * Obtiene el perfil empresarial asociado a un número de teléfono.
     *
     * @param string $phoneNumberId El ID del número de teléfono.
     * @return array La respuesta de la API con los detalles del perfil empresarial.
     */
    public function getBusinessProfile(string $phoneNumberId): array
    {
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

    /**
     * Configura un token temporal para realizar solicitudes a la API.
     *
     * @param string $token El token temporal.
     * @return self
     */
    public function withTempToken(string $token): self
    {
        // Resolver el modelo configurado para la cuenta empresarial
        $businessAccount = WhatsappModelResolver::make('business_account', ['api_token' => $token]);

        // Asignar la instancia del modelo a la propiedad
        $this->businessAccount = $businessAccount;

        return $this;
    }

    /**
     * Actualiza una cuenta empresarial
     */
    public function updateBusinessAccount(string $businessAccountId, array $data): Model
    {
        $account = $this->accountRepo->find($businessAccountId);
        
        if (isset($data['api_token'])) {
            $account->api_token = $data['api_token'];
        }
        
        $account->fill($data);
        $account->save();
        
        return $account;
    }

    /**
     * Suscribe una aplicación a la cuenta empresarial
     * 
     * @param string $whatsappBusinessId
     * @return array
     */
    public function subscribeApp(string $whatsappBusinessId): array
    {
        $this->ensureAccountIsSet();
        
        $endpointUrl = Endpoints::build(
            Endpoints::GET_BUSINESS_ACCOUNT_SUSCRIPTIONS,
            ['whatsapp_business_id' => $whatsappBusinessId]
        );
        
        return $this->apiClient->request(
            'POST',
            $endpointUrl,
            [], // Sin parámetros de query
            [], // Cuerpo vacío como requiere la API
            ['headers' => $this->getAuthHeaders()]
        );
    }

    /**
     * Registra un nuevo número telefónico
     */
    public function registerPhoneNumber(string $businessAccountId, array $phoneData): Model
    {
        $this->forAccount($businessAccountId);
        $account = $this->accountRepo->find($businessAccountId);
        $registrationService = app(AccountRegistrationService::class); // Resuelve aquí
        return $registrationService->registerSinglePhoneNumber($account, $phoneData);
    }

    /**
     * Elimina un número telefónico
     */
    public function deletePhoneNumber(string $phoneNumberId): bool
    {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        
        if ($phone) {
            if ($phone->businessProfile) {
                $phone->businessProfile->delete();
            }
            return $phone->delete();
        }
        
        return false;
    }

    /**
     * Configura el webhook para un número
     */
    public function configureWebhook(string $phoneNumberId, string $url, string $verifyToken): array
    {
        $this->ensureAccountIsSet();

        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) {
            throw new \RuntimeException('Número telefónico no encontrado');
        }
        
        $apiPhoneId = $phone->api_phone_number_id;

        $endpointUrl = Endpoints::build(Endpoints::CONFIGURE_WEBHOOK, [
            'phone_number_id' => $apiPhoneId
        ]);
        
        Log::channel('whatsapp')->debug('Configurando webhook', [
            'url' => $endpointUrl,
            'webhook_url' => $url,
            'verify_token' => $verifyToken
        ]);

        Log::channel('whatsapp')->debug('Configurando webhook', [
            'url' => $endpointUrl,
            'webhook_url' => $url,
            'verify_token' => $verifyToken
        ]);
        
        $response = $this->apiClient->request(
            'POST',
            $endpointUrl,
            [], // Parámetros de consulta
            [   // Cuerpo de la solicitud
                'webhook_url' => $url,
                'verify_token' => $verifyToken,
            ],
            [   // Opciones
                'headers' => $this->getAuthHeaders()
            ]
        );
        
        $phone->update([
            'webhook_configuration' => [
                'url' => $url,
                'verify_token' => $verifyToken
            ]
        ]);
        
        return $response;
    }
}