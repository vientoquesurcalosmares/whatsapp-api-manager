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
        protected WhatsappService $whatsappService
    ) {}

    public function register(array $data): WhatsappBusinessAccount
    {
        Log::channel('whatsapp')->info('Iniciando registro de cuenta', ['business_id' => $data['business_id']]);
        
        $this->validateInput($data);

        try {
            // 1. Registrar/Actualizar cuenta empresarial (el token se encripta automáticamente)
            $accountData = $this->fetchAccountData($data);
            $account = $this->upsertBusinessAccount($data['api_token'], $accountData);
            
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

    private function validateInput(array $data): void
    {
        if (empty($data['api_token']) || empty($data['business_id'])) {
            throw new \InvalidArgumentException('Token API y Business ID son requeridos');
        }
    }

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

    private function upsertBusinessAccount(string $apiToken, array $apiData): WhatsappBusinessAccount
    {
        // El token se encripta automáticamente al guardar (vía mutador)
        return WhatsappBusinessAccount::updateOrCreate(
            ['whatsapp_business_id' => $apiData['id']],
            [
                'name' => $apiData['name'] ?? 'Sin nombre',
                'api_token' => $apiToken, // Se encripta aquí
                'phone_number_id' => $apiData['phone_number_id'] ?? $apiData['id'],
                'timezone_id' => $apiData['timezone_id'] ?? 0,
                'message_template_namespace' => $apiData['message_template_namespace'] ?? null
            ]
        );
    }

    private function registerPhoneNumbers(WhatsappBusinessAccount $account): void
    {
        try {
            // Usa el token DESENCRIPTADO automáticamente (vía accessor)
            $response = $this->whatsappService
                ->forAccount($account->whatsapp_business_id)
                ->getPhoneNumbers($account->whatsapp_business_id);

            foreach ($response as $phoneData) {
                $this->updateOrCreatePhoneNumber($account, $phoneData);
            }
        } catch (ApiException $e) {
            Log::channel('whatsapp')->error("Error números telefónicos: {$e->getMessage()}");
            throw $e;
        }
    }

    private function updateOrCreatePhoneNumber(WhatsappBusinessAccount $account, array $phoneData): WhatsappPhoneNumber
    {
        try {
            $phoneDetails = $this->whatsappService->getPhoneNumberDetails($phoneData['id']);
            
            // Extraer código de país y número
            preg_match('/^\+(\d+)\s*(.+)$/', $phoneDetails['display_phone_number'], $matches);
            $countryCode = $matches[1] ?? null;
            $phoneNumber = preg_replace('/\s+/', '', $matches[2] ?? '');

            return WhatsappPhoneNumber::updateOrCreate(
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

            if (!isset($profileData['data'][0])) {
                throw new InvalidApiResponseException("Perfil no encontrado");
            }

            $this->upsertBusinessProfile($phone, $profileData['data'][0]);
        } catch (ApiException | InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Error en perfil: {$e->getMessage()}");
            throw $e;
        }
    }

    private function upsertBusinessProfile(WhatsappPhoneNumber $phone, array $profileData): void
    {
        try {
            $validator = new BusinessProfileValidator();
            $validData = $validator->validate($profileData);

            $profile = WhatsappBusinessProfile::updateOrCreate(
                ['whatsapp_business_profile_id' => $validData['id'] ?? Str::ulid()],
                $validData
            );

            $profile = $phone->whatsapp_business_profile_id 
            ? WhatsappBusinessProfile::find($phone->whatsapp_business_profile_id)
            : null;

            if ($profile) {
                // Actualizar perfil existente
                $profile->update($validData);
            } else {
                // Crear nuevo perfil solo si no existe
                $profile = WhatsappBusinessProfile::create($validData);
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

    private function parseWebsites(array $apiWebsites): array
    {
        return array_map(fn($website) => [
            'website' => is_array($website) ? ($website['url'] ?? null) : $website
        ], $apiWebsites);
    }

    private function syncWebsites(WhatsappBusinessProfile $profile, array $websites): void
    {
        $profile->websites()->delete();
        
        foreach ($websites as $website) {
            if (!empty($website['website']) && filter_var($website['website'], FILTER_VALIDATE_URL)) {
                $profile->websites()->create($website);
            }
        }
    }
}