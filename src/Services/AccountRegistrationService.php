<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ScriptDevelop\WhatsappManager\Helpers\CountryCodes;
//use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
//use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
//use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessProfile;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;
use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;
use ScriptDevelop\WhatsappManager\Exceptions\InvalidApiResponseException;
use ScriptDevelop\WhatsappManager\WhatsappApi\Validators\BusinessProfileValidator;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

/**
 * Servicio para registrar cuentas empresariales de WhatsApp y gestionar sus números telefónicos.
 * Este servicio se encarga de validar la entrada, registrar o actualizar cuentas empresariales,
 * registrar números telefónicos y vincular perfiles empresariales a los números.
 */
class AccountRegistrationService
{
    /**
     * @var WhatsappService
     */
    use GeneratesUlid;

    /**
     * Constructor del servicio de registro de cuentas empresariales.
     *
     * @param WhatsappService $whatsappService Servicio de WhatsApp.
     */
    public function __construct(
        protected WhatsappService $whatsappService
    ) {}

    /**
     * Registra una cuenta empresarial de WhatsApp y sus números telefónicos.
     *
     * @param array $data Datos de la cuenta empresarial.
     * @return Model La cuenta empresarial registrada o actualizada.
     * @throws ApiException Si ocurre un error al interactuar con la API de WhatsApp.
     * @throws InvalidApiResponseException Si la respuesta de la API es inválida.
     */
    public function register(array $data): Model
    {
        Log::channel('whatsapp')->info('Iniciando registro de cuenta', ['business_id' => $data['business_id']]);

        $this->validateInput($data);

        try {
            // 1. Registrar/Actualizar cuenta empresarial (el token se encripta automáticamente)
            $accountData = $this->fetchAccountData($data);
            $suscriptions = $this->fetchAccountDataSuscriptions($data);

            $account = $this->upsertBusinessAccount($data['api_token'], $accountData, $suscriptions);

            // 2. Registrar números telefónicos
            $this->registerPhoneNumbers($account);

            // 3. Vincular perfiles empresariales
            $this->linkBusinessProfilesToPhones($account);

            return $account->load(['phoneNumbers.businessProfile']);

        } catch (ApiException | InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Error registro cuenta: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Valida la entrada de datos para el registro de la cuenta empresarial.
     *
     * @param array $data Datos de la cuenta empresarial.
     * @throws \InvalidArgumentException Si los datos son inválidos.
     */
    private function validateInput(array $data): void
    {
        if (empty($data['api_token']) || empty($data['business_id'])) {
            throw new \InvalidArgumentException('Token API y Business ID son requeridos');
        }
    }

    /**
     * Obtiene los datos de la cuenta empresarial desde la API de WhatsApp.
     *
     * @param array $data Datos de la cuenta empresarial.
     * @return array Datos de la cuenta empresarial.
     * @throws ApiException Si ocurre un error al interactuar con la API de WhatsApp.
     */
    protected function fetchAccountData(array $data): array
    {
        // Usa el token temporal SIN encriptar para la verificación inicial
        $response = $this->whatsappService
            ->withTempToken($data['api_token'])
            ->getBusinessAccount($data['business_id']);

        Log::channel('whatsapp')->debug('RESPUESTA API BUSINESS ACCOUNT:', [
            'business_id' => $data['business_id'],
            'response' => $response
        ]);

        return $response;
    }

    protected function fetchAccountDataSuscriptions(array $data): array
    {
        // Usa el token temporal SIN encriptar para la verificación inicial
        $response = $this->whatsappService
            ->withTempToken($data['api_token'])
            ->getBusinessAccountApp($data['business_id']);

        Log::channel('whatsapp')->debug('RESPUESTA API BUSINESS ACCOUNT SUSCRIPTIONS:', [
            'business_id' => $data['business_id'],
            'response' => $response
        ]);

        return $response;
    }

    /**
     * Registra o actualiza la cuenta empresarial en la base de datos.
     *
     * @param string $apiToken Token de la API.
     * @param array $apiData Datos de la cuenta empresarial.
     * @return Model La cuenta empresarial registrada o actualizada.
     */
    private function upsertBusinessAccount(string $apiToken, array $apiData, $subscriptions = null): Model
    {
        $appData = [];

        if (!empty($subscriptions['data'][0]['whatsapp_business_api_data'])) {
            $appData = $subscriptions['data'][0]['whatsapp_business_api_data'];
        }

        // El token se encripta automáticamente al guardar (vía mutador)
        return WhatsappModelResolver::business_account()->updateOrCreate(
            ['whatsapp_business_id' => $apiData['id']],
            [
                'name' => $apiData['name'] ?? 'Sin nombre',
                'api_token' => $apiToken, // Se encripta aquí
                'phone_number_id' => $apiData['phone_number_id'] ?? $apiData['id'],
                'timezone_id' => $apiData['timezone_id'] ?? 0,
                'app_id' => $appData['id'] ?? null,          // ID de la primera app
                'app_name' => $appData['name'] ?? null,      // Nombre de la primera app
                'app_link' => $appData['link'] ?? null,      // Link de la primera app
                'message_template_namespace' => $apiData['message_template_namespace'] ?? null
            ]
        );
    }

    /**
     * Registra los números telefónicos asociados a la cuenta empresarial.
     *
     * @param Model $account La cuenta empresarial.
     * @throws ApiException Si ocurre un error al interactuar con la API de WhatsApp.
     */
    private function registerPhoneNumbers(Model $account): void
    {
        try {
            $whatsappService = app(WhatsappService::class)->forAccount($account->whatsapp_business_id);
    
            $response = $whatsappService->getPhoneNumbers($account->whatsapp_business_id);

            foreach ($response as $phoneData) {
                $this->updateOrCreatePhoneNumber($account, $phoneData);
            }
        } catch (ApiException $e) {
            Log::channel('whatsapp')->error("Error números telefónicos: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Actualiza o crea un número telefónico en la base de datos.
     *
     * @param Model $account La cuenta empresarial.
     * @param array $phoneData Datos del número telefónico.
     * @return Model El número telefónico registrado o actualizado.
     * @throws ApiException Si ocurre un error al interactuar con la API de WhatsApp.
     */
    private function updateOrCreatePhoneNumber(Model $account, array $phoneData): Model
    {
        try {
            $phoneDetails = $this->whatsappService->getPhoneNumberDetails($phoneData['id']);

            // Obtener todos los códigos de país ordenados por longitud (de mayor a menor)
            $countryCodes = CountryCodes::codes();
            usort($countryCodes, function($a, $b) {
                return strlen($b) <=> strlen($a);
            });

            // Extraer código de país y número
            $rawNumber = preg_replace('/[^\d]/', '', $phoneDetails['display_phone_number']);
            $countryCode = null;
            $phoneNumber = $rawNumber;

            // Buscar el código de país más largo que coincida al principio
            foreach ($countryCodes as $code) {
                if (strpos($rawNumber, $code) === 0) {
                    $countryCode = $code;
                    $phoneNumber = substr($rawNumber, strlen($code));
                    break;
                }
            }

            return WhatsappModelResolver::phone_number()->updateOrCreate(
                ['api_phone_number_id' => $phoneData['id']],
                [
                    'whatsapp_business_account_id' => $account->whatsapp_business_id,
                    'display_phone_number' => $phoneDetails['display_phone_number'],
                    'country_code' => $countryCode,
                    'phone_number' => $phoneNumber,
                    'verified_name' => $phoneDetails['verified_name'],
                    'code_verification_status' => $phoneDetails['code_verification_status'],
                    'quality_rating' => $phoneDetails['quality_rating'],
                    'platform_type' => $phoneDetails['platform_type'],
                    'throughput' => $phoneDetails['throughput'] ?? null,
                    'api_phone_number_id' => $phoneData['id'],
                    'webhook_configuration' => $phoneDetails['webhook_configuration'] ?? null
                ]
            );
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error al guardar número', [
                'error' => $e->getMessage(),
                'data' => $phoneData
            ]);
            throw $e;
        }
    }

    /**
     * Vincula los perfiles empresariales a los números telefónicos.
     *
     * @param Model $account La cuenta empresarial.
     * @throws ApiException Si ocurre un error al interactuar con la API de WhatsApp.
     */
    private function linkBusinessProfilesToPhones(Model $account): void
    {
        foreach ($account->phoneNumbers as $phone) {
            $this->processPhoneNumberProfile($phone);
        }
    }

    /**
     * Procesa el perfil empresarial de un número telefónico.
     *
     * @param Model $phone El número telefónico.
     * @throws ApiException Si ocurre un error al interactuar con la API de WhatsApp.
     * @throws InvalidApiResponseException Si la respuesta de la API es inválida.
     */
    private function processPhoneNumberProfile(Model $phone): void
    {
        try {
            $profileData = $this->whatsappService
                ->forAccount($phone->whatsapp_business_account_id)
                ->getBusinessProfile($phone->api_phone_number_id);

            if (!isset($profileData['data'][0])) {
                throw new InvalidApiResponseException("Perfil no encontrado");
            }

            $this->upsertBusinessProfile($phone, $profileData['data'][0]);
        } catch (ApiException | InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Error en perfil: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Actualiza o crea un perfil empresarial en la base de datos.
     *
     * @param Model $phone El número telefónico.
     * @param array $profileData Datos del perfil empresarial.
     * @throws InvalidApiResponseException Si la respuesta de la API es inválida.
     */
    private function upsertBusinessProfile(Model $phone, array $profileData): void
    {
        try {
            $validator = new BusinessProfileValidator();
            $validData = $validator->validate($profileData);

            $profile = $phone->businessProfile;

            if ($profile) {
                // Actualizar perfil existente
                $profile->update($validData);
            } else {
                // Crear nuevo perfil solo si no existe
                $profile = WhatsappModelResolver::business_profile()->create($validData);
                $phone->update(['whatsapp_business_profile_id' => $profile->whatsapp_business_profile_id]);
            }

            // Sincronizar websites
            $this->syncWebsites(
                $profile,
                $this->parseWebsites($profileData['websites'] ?? [])
            );
        } catch (InvalidApiResponseException $e) {
            throw $e;
        }
    }

    /**
     * Convierte los datos de los sitios web a un formato adecuado para la base de datos.
     *
     * @param array $apiWebsites Datos de los sitios web desde la API.
     * @return array Datos de los sitios web convertidos.
     */
    private function parseWebsites(array $apiWebsites): array
    {
        return array_map(fn($website) => [
            'website' => is_array($website) ? ($website['url'] ?? null) : $website
        ], $apiWebsites);
    }

    /**
     * Sincroniza los sitios web del perfil empresarial en la base de datos.
     *
     * @param Model $profile El perfil empresarial.
     * @param array $websites Datos de los sitios web.
     */
    private function syncWebsites(Model $profile, array $websites): void
    {
        $profile->websites()->delete();

        foreach ($websites as $website) {
            if (!empty($website['website']) && filter_var($website['website'], FILTER_VALIDATE_URL)) {
                $profile->websites()->create($website);
            }
        }
    }

    /**
     * Registra un solo número telefónico
     */
    public function registerSinglePhoneNumber(Model $account, array $phoneData): Model
    {
        $phone = $this->updateOrCreatePhoneNumber($account, $phoneData);
        $this->processPhoneNumberProfile($phone);
        return $phone;
    }
}