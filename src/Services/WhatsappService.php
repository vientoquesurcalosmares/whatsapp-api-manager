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
            throw new \RuntimeException(whatsapp_trans('messages.whatsapp_must_set_account_first'));
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
            null,
            ['fields' => 'id,name,timezone_id,whatsapp_business_manager_messaging_limit,message_template_namespace'],
            $this->getAuthHeaders()
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
            Endpoints::GET_BUSINESS_ACCOUNT_SUBSCRIPTIONS, // Cambiado a SUBSCRIPTIONS
            ['whatsapp_business_id' => $whatsappBusinessId],
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de getBusinessAccountApp:', $response);
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
     * Suscribe una aplicación a la cuenta empresarial de WhatsApp actual
     *
     * @param array $subscribedFields Campos a suscribir (opcional)
     * @return array
     */
    public function subscribeApp(?array $subscribedFields = null): array
    {
        $this->ensureAccountIsSet();
        
        // Si no se proporcionan campos, usar los de configuración
        if ($subscribedFields === null) {
            $subscribedFields = config('whatsapp-manager.webhook.subscribed_fields', []);
        }
        
        $requestBody = [];
        if (!empty($subscribedFields)) {
            $requestBody['subscribed_fields'] = $subscribedFields;
        }
        
        Log::channel('whatsapp')->debug('Suscribiendo aplicación', [
            'business_id' => $this->businessAccount->whatsapp_business_id,
            'subscribed_fields' => $subscribedFields
        ]);
        
        $response = $this->apiClient->request(
            'POST',
            Endpoints::SUBSCRIBE_APP,
            ['whatsapp_business_id' => $this->businessAccount->whatsapp_business_id],
            $requestBody,
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de subscribeApp:', $response);
        return $response;
    }

    /**
     * Obtiene los campos suscritos actualmente para una cuenta empresarial
     * 
     * @param string $whatsappBusinessId
     * @return array
     */
    public function getSubscribedFields(string $whatsappBusinessId): array
    {
        $this->ensureAccountIsSet();
        
        $response = $this->apiClient->request(
            'GET',
            Endpoints::GET_BUSINESS_ACCOUNT_SUBSCRIPTIONS,
            ['whatsapp_business_id' => $whatsappBusinessId],
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Campos suscritos actuales:', $response);
        
        // Extraer campos suscritos de la respuesta
        $subscribedFields = [];
        if (isset($response['data'][0]['subscribed_fields'])) {
            $subscribedFields = $response['data'][0]['subscribed_fields'];
        }
        
        return $subscribedFields;
    }

    /**
     * Actualiza los campos suscritos para una cuenta empresarial
     * 
     * @param string $whatsappBusinessId
     * @param array $subscribedFields
     * @return array
     */
    public function updateSubscribedFields(string $whatsappBusinessId, array $subscribedFields): array
    {
        // Primero establecemos la cuenta
        $this->forAccount($whatsappBusinessId);
        // Luego llamamos a subscribeApp con los campos
        return $this->subscribeApp($subscribedFields);
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
            throw new \RuntimeException(whatsapp_trans('messages.whatsapp_phone_number_not_found'));
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

    /**
     * Obtiene las aplicaciones suscritas a la cuenta empresarial actual
     *
     * @return array
     */
    public function subscribedApps(): array
    {
        $this->ensureAccountIsSet();
        
        $response = $this->apiClient->request(
            'GET',
            Endpoints::GET_BUSINESS_ACCOUNT_SUBSCRIPTIONS,
            ['whatsapp_business_id' => $this->businessAccount->whatsapp_business_id],
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de subscribedApps:', $response);
        return $response;
    }

    /**
     * Cancela la suscripción de una aplicación a la cuenta empresarial actual
     *
     * @return array
     */
    public function unsubscribeApp(): array
    {
        $this->ensureAccountIsSet();
        
        Log::channel('whatsapp')->debug('Cancelando suscripción de aplicación', [
            'business_id' => $this->businessAccount->whatsapp_business_id
        ]);
        
        $response = $this->apiClient->request(
            'DELETE',
            Endpoints::UNSUBSCRIBE_APP,
            ['whatsapp_business_id' => $this->businessAccount->whatsapp_business_id],
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de unsubscribeApp:', $response);
        return $response;
    }

    /**
     * Registra un número telefónico en la API de WhatsApp
     * Nota: Limitado a 10 solicitudes por número de negocio en 72 horas
     *
     * @param string $phoneNumberId
     * @param array $data Datos de registro
     * @return array
     */
    public function registerPhone(string $phoneNumberId, array $data = []): array
    {
        $this->ensureAccountIsSet();
        
        // Campos por defecto para el registro
        $fields = $data['fields'] ?? 'primary_funding_id';
        
        $url = Endpoints::build(
            Endpoints::GET_BUSINESS_ACCOUNT,
            ['whatsapp_business_id' => $phoneNumberId]
        ) . '?' . http_build_query(['fields' => $fields]);

        Log::channel('whatsapp')->debug('Registrando número telefónico', [
            'phone_number_id' => $phoneNumberId,
            'fields' => $fields
        ]);
        
        $response = $this->apiClient->request(
            'GET',
            $url,
            headers: $this->getAuthHeaders()
        );

        Log::channel('whatsapp')->debug('Respuesta de registerPhone:', $response);
        return $response;
    }
}