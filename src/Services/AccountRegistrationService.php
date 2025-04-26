<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Illuminate\Support\Facades\Log;
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

            foreach ($response['data'] ?? [] as $phoneData) {
                $this->updateOrCreatePhoneNumber($account, $phoneData);
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
        return WhatsappPhoneNumber::updateOrCreate(
            ['phone_number_id' => $phoneData['id']], // Usar 'id' directamente de la respuesta
            [
                'whatsapp_business_account_id' => $account->whatsapp_business_id,
                'display_phone_number' => $phoneData['display_phone_number'],
                'verified_name' => $phoneData['verified_name'],
                'phone_number_id' => $phoneData['id'] // <-- Añadir esta línea
            ]
        );
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
                ->getBusinessProfile($phone->phone_number_id);

            $this->upsertBusinessProfile($phone, $profileData['data'][0] ?? []);

        } catch (ApiException | InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Error perfil para número {$phone->id}: {$e->getMessage()}");
        }
    }

    private function upsertBusinessProfile(WhatsappPhoneNumber $phone, array $profileData): void
    {
        try {
            $validator = new BusinessProfileValidator();
            $validData = $validator->validate($profileData);
            
            // Vincular perfil al número telefónico
            $validData['whatsapp_business_profile_id'] = $phone->whatsapp_phone_id;

            // Crear/actualizar perfil
            $profile = WhatsappBusinessProfile::updateOrCreate(
                ['whatsapp_business_profile_id' => $phone->whatsapp_phone_id],
                $validData
            );

            // Sincronizar websites
            $websitesData = $validator->extractWebsites($profileData);
            $this->syncWebsites($profile, $websitesData);

            // Vincular perfil al número
            $phone->update(['whatsapp_business_profile_id' => $profile->id]);

        } catch (InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Error perfil: {$e->getMessage()}");
        }
    }

    private function syncWebsites(WhatsappBusinessProfile $profile, array $websites): void
    {
        $profile->websites()->delete();
        
        foreach ($websites as $website) {
            $profile->websites()->create(['website' => $website['url']]);
        }
    }
}