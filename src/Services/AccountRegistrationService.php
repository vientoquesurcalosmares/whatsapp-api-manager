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

    private function fetchAccountData(array $data): array
    {
        return $this->whatsappService
            ->withTempToken($data['api_token'])
            ->getBusinessAccount($data['business_id']);
    }

    private function upsertBusinessAccount(string $apiToken, array $apiData): WhatsappBusinessAccount
    {
        return WhatsappBusinessAccount::updateOrCreate(
            ['whatsapp_business_id' => $apiData['id']],
            [
                'name' => $apiData['name'] ?? 'Sin nombre',
                'api_token' => $apiToken,
                'phone_number_id' => $apiData['phone_number_id'] ?? null,
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

    private function updateOrCreatePhoneNumber(WhatsappBusinessAccount $account, array $phoneData): WhatsappPhoneNumber
    {
        return WhatsappPhoneNumber::updateOrCreate(
            ['phone_number_id' => $phoneData['id']],
            [
                'whatsapp_business_account_id' => $account->whatsapp_business_id,
                'display_phone_number' => $phoneData['display_phone_number'],
                'verified_name' => $phoneData['verified_name'],
                'whatsapp_business_profile_id' => null // Se actualizará después
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
        $validator = new BusinessProfileValidator();
        $validData = $validator->validate($profileData);
        
        $profile = WhatsappBusinessProfile::updateOrCreate(
            ['whatsapp_business_account_id' => $phone->whatsapp_business_account_id],
            $validData
        );

        // Vincular perfil con el número telefónico
        if ($phone->whatsapp_business_profile_id !== $profile->id) {
            $phone->update(['whatsapp_business_profile_id' => $profile->id]);
        }
        
        $this->syncProfileWebsites($profile, $validator->extractWebsites($profileData));
    }

    private function syncProfileWebsites(WhatsappBusinessProfile $profile, array $websites): void
    {
        $profile->websites()->delete();
        
        foreach ($websites as $website) {
            $profile->websites()->create(['website' => $website['url']]);
        }
    }
}