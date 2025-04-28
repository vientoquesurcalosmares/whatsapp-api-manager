<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessProfile;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;
use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;
use ScriptDevelop\WhatsappManager\Exceptions\InvalidApiResponseException;
use ScriptDevelop\WhatsappManager\WhatsappApi\Validators\BusinessProfileValidator;

class AccountRegistrationService
{
    use GeneratesUlid;

    public function __construct(
        protected WhatsappService $whatsappPhoneService // Renombrar variable
    ) {}

    public function register(array $data): WhatsappBusinessAccount
    {
        Log::channel('whatsapp')->info('Iniciando registro de cuenta', ['business_id' => $data['business_id']]);
        
        $this->validateInput($data);

        try {
            // 1. Verificar y registrar/actualizar la cuenta empresarial
            $accountData = $this->fetchAccountData($data);
            $account = $this->upsertBusinessAccount($data['api_token'], $accountData);
            
            // 2. Registrar números telefónicos asociados
            $this->registerPhoneNumbers($account);
            
            // 3. Obtener y vincular perfiles empresariales
            $this->linkBusinessProfilesToPhones($account);

            return $account->load(['phoneNumbers.businessProfile']);

        } catch (ApiException | InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Error registro cuenta: {$e->getMessage()}");
            throw $e;
        }
    }

    private function validateInput(array $data): void
    {
        if (empty($data['api_token']) || empty($data['business_id'])) {
            throw new \InvalidArgumentException('Token API y Business ID son requeridos');
        }
    }

    protected function fetchAccountData(array $data): array
    {
        $response = $this->whatsappService
            ->withTempToken($data['api_token'])
            ->getBusinessAccount($data['business_id']);

        // Debug: Registrar respuesta completa de la API
        Log::channel('whatsapp')->debug('RESPUESTA CRUDA DE LA API BUSINESS ACCOUNT:', [
            'Business ID solicitado' => $data['business_id'],
            'Respuesta completa' => $response
        ]);
        
        return $response;
    }

    private function upsertBusinessAccount(string $apiToken, array $apiData): WhatsappBusinessAccount
    {
        return WhatsappBusinessAccount::updateOrCreate(
            ['whatsapp_business_id' => $apiData['id']],
            [
                'name' => $apiData['name'] ?? 'Sin nombre',
                'api_token' => $apiToken,
                'phone_number_id' => $apiData['phone_number_id'] ?? $apiData['id'], // Usar business_id como fallback
                'timezone_id' => $apiData['timezone_id'] ?? 0,
                'message_template_namespace' => $apiData['message_template_namespace'] ?? null
            ]
        );
    }

    private function registerPhoneNumbers(WhatsappBusinessAccount $account): void
    {
        try {
            $response = $this->whatsappService
            ->forAccount($account->whatsapp_business_id)
            ->getPhoneNumbers($account->whatsapp_business_id);

            Log::channel('whatsapp')->debug('Respuesta de getPhoneNumbers Service:', $response);

            foreach ($response as $phoneData) {
                Log::channel('whatsapp')->debug('Procesando número:', $phoneData);
                $phone = $this->updateOrCreatePhoneNumber($account, $phoneData);
                Log::channel('whatsapp')->debug('Número guardado:', $phone->toArray());
            }
        } catch (ApiException $e) {
            Log::channel('whatsapp')->error("Error números telefónicos: {$e->getMessage()}");
            throw $e;
        }
    }

    private function registerSinglePhoneNumber(WhatsappBusinessAccount $account, array $phoneData): void 
    {
        try {
            WhatsappPhoneNumber::updateOrCreate(
                ['phone_number_id' => $phoneData['id']],
                [
                    'whatsapp_business_account_id' => $account->whatsapp_business_id,
                    'display_phone_number' => $phoneData['display_phone_number'],
                    'verified_name' => $phoneData['verified_name']
                ]
            );
            
            Log::channel('whatsapp')->debug('Número registrado:', $phoneData);
            
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error guardando número', [
                'error' => $e->getMessage(),
                'data' => $phoneData
            ]);
        }
    }

    private function updateOrCreatePhoneNumber(WhatsappBusinessAccount $account, array $phoneData): WhatsappPhoneNumber
    {
        try {
            // Obtener detalles adicionales del número
            $phoneDetails = $this->whatsappService->getPhoneNumberDetails($phoneData['id']);
            
            // return WhatsappPhoneNumber::updateOrCreate(
            //     ['api_phone_number_id' => $phoneData['id']],
            //     [
            //         'whatsapp_business_account_id' => $account->whatsapp_business_id,
            //         'display_phone_number' => $phoneData['display_phone_number'],
            //         'verified_name' => $phoneData['verified_name'],
            //         'api_phone_number_id' => $phoneData['id']
            //     ]
            // );
            return WhatsappPhoneNumber::updateOrCreate(
                ['api_phone_number_id' => $phoneData['id']],
                [
                    'whatsapp_business_account_id' => $account->whatsapp_business_id,
                    'display_phone_number' => $phoneDetails['display_phone_number'],
                    'verified_name' => $phoneDetails['verified_name'],
                    'code_verification_status' => $phoneDetails['code_verification_status'],
                    'quality_rating' => $phoneDetails['quality_rating'],
                    'platform_type' => $phoneDetails['platform_type'],
                    'throughput' => $phoneDetails['throughput'] ?? null,
                    'webhook_configuration' => $phoneDetails['webhook_configuration'] ?? null,
                    'api_phone_number_id' => $phoneData['id']
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

    private function linkBusinessProfilesToPhones(WhatsappBusinessAccount $account): void
    {
        foreach ($account->phoneNumbers as $phone) {
            $this->processPhoneNumberProfile($phone);
        }
    }

    private function processPhoneNumberProfile(WhatsappPhoneNumber $phone): void
    {
        try {
            $profileData = $this->whatsappService
                ->forAccount($phone->whatsapp_business_account_id)
                ->getBusinessProfile($phone->api_phone_number_id);

            Log::channel('whatsapp')->debug('Datos del perfil recibidos:', $profileData);

            if (!isset($profileData['data'][0])) {
                Log::channel('whatsapp')->error('Perfil no encontrado en la respuesta');
                throw new InvalidApiResponseException("No se encontró perfil en la respuesta");
            }

            $perfil = $profileData['data'][0];
            Log::channel('whatsapp')->debug('Perfil extraído para validar:', $perfil);

            $this->upsertBusinessProfile($phone, $profileData['data'][0]);
        } catch (ApiException | InvalidApiResponseException $e) {
            // Log::channel('whatsapp')->error("Error perfil para número {$phone->phone_number_id}: {$e->getMessage()}");
            Log::channel('whatsapp')->error("Error en Perfil: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString() // Detalles del error
            ]);
            throw $e;
        }
    }

    private function upsertBusinessProfile(WhatsappPhoneNumber $phone, array $profileData): void
    {
        try {
            
            $validator = new BusinessProfileValidator();
            $validData = $validator->validate($profileData);

            // 1. Crear/Actualizar el perfil (con ULID generado automáticamente)
            $profile = WhatsappBusinessProfile::updateOrCreate(
                ['whatsapp_business_profile_id' => $validData['id'] ?? Str::ulid()],
                $validData
            );

            // 2. Vincular el perfil al número telefónico
            $phone->whatsapp_business_profile_id = $profile->whatsapp_business_profile_id;
            $phone->save();

            // 3. Sincronizar websites
            $websitesData = $this->parseWebsites($profileData['websites'] ?? []);
            $this->syncWebsites($profile, $websitesData);

        } catch (InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Error en perfil upsertBusinessProfile: {$e->getMessage()}", [
                'profile_data' => $profileData
            ]);

            throw $e;
        }
    }

    private function parseWebsites(array $apiWebsites): array
    {
        return array_map(function ($website) {
            // Si es un array, extraer 'url'; si es string, usarlo directamente
            $url = is_array($website) ? ($website['url'] ?? null) : $website;
            return ['website' => $url];
        }, $apiWebsites);
    }

    private function syncWebsites(WhatsappBusinessProfile $profile, array $websites): void
    {
        $profile->websites()->delete();
        
        foreach ($websites as $website) {
            // Filtrar URLs vacías o inválidas
            if (!empty($website['website']) && filter_var($website['website'], FILTER_VALIDATE_URL)) {
                $profile->websites()->create(['website' => $website['website']]);
            }
        }
    }
}