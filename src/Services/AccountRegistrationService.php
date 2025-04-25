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
            Log::channel('whatsapp')->debug('Obteniendo datos de la cuenta...');
            $accountData = $this->fetchAccountData($data);
            Log::channel('whatsapp')->debug('Datos de cuenta obtenidos:', $accountData);

            $account = $this->upsertBusinessAccount($data['api_token'], $accountData);
            Log::channel('whatsapp')->info('Cuenta empresarial creada/actualizada:', $account->toArray());

            Log::channel('whatsapp')->debug('Registrando números telefónicos...');
            $this->registerPhoneNumbers($account);
            
            return $account->load('phoneNumbers');

        } catch (ApiException | InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Error registro cuenta: {$e->getMessage()}", ['exception' => $e]);
            throw $e;
        }
    }


    protected function validateInput(array $data): void
    {
        if (empty($data['api_token'])) {
            throw new \InvalidArgumentException('El token de API es requerido');
        }

        if (empty($data['business_id'])) {
            throw new \InvalidArgumentException('El ID de la cuenta es requerido');
        }
    }

    protected function fetchAccountData(array $data): array
    {
        return $this->whatsappService
            ->withTempToken($data['api_token'])
            ->getBusinessAccount($data['business_id']);
    }

    protected function upsertBusinessAccount(string $apiToken, array $apiData): WhatsappBusinessAccount
    {
        return WhatsappBusinessAccount::updateOrCreate(
            ['whatsapp_business_id' => $apiData['id']],
            [
                'name' => $apiData['name'] ?? 'Cuenta sin nombre',
                'api_token' => $apiToken,
                'phone_number_id' => $apiData['phone_number_id'] ?? $apiData['id'],
                'timezone_id' => $apiData['timezone_id'] ?? 0,
                'message_template_namespace' => $apiData['message_template_namespace'] ?? null
            ]
        );
    }

    protected function registerPhoneNumbers(WhatsappBusinessAccount $account): void
    {
        try {
            Log::channel('whatsapp')->debug('Solicitando números telefónicos...');
            $response = $this->whatsappService
                ->forAccount($account->whatsapp_business_id)
                ->getPhoneNumbers($account->whatsapp_business_id);

            Log::channel('whatsapp')->debug('Respuesta de números telefónicos:', $response);
            $phoneNumbers = $response['data'] ?? [];

            if (empty($phoneNumbers)) {
                Log::channel('whatsapp')->warning('No se encontraron números telefónicos en la respuesta');
                return;
            }

            foreach ($phoneNumbers as $phoneData) {
                $this->registerSinglePhoneNumber($account, $phoneData);
            }

        } catch (ApiException $e) {
            Log::channel('whatsapp')->error("Error números telefónicos: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function registerSinglePhoneNumber(WhatsappBusinessAccount $account, array $phoneData): void
    {
        $phone = WhatsappPhoneNumber::updateOrCreate(
            ['phone_number_id' => $phoneData['id']],
            [
                'whatsapp_business_account_id' => $account->whatsapp_business_id,
                'display_phone_number' => $phoneData['display_phone_number'],
                'verified_name' => $phoneData['verified_name']
            ]
        );

        $this->registerBusinessProfile($phone);
    }

    protected function registerBusinessProfile(WhatsappPhoneNumber $phone): void
    {
        try {
            // Obtener perfil existente del número de teléfono
            $profile = $phone->businessProfile;

            // Obtener datos del perfil desde la API
            $response = $this->whatsappService
                ->forAccount($phone->whatsapp_business_account_id)
                ->getBusinessProfile($phone->phone_number_id);

            $businessProfile = $response['data'][0] ?? [];
            if (empty($businessProfile)) {
                Log::channel('whatsapp')->error('Estructura de perfil inválida');
                return;
            }

            $validator = new BusinessProfileValidator();
            $validData = $validator->validate($businessProfile);
            $validatedWebsites = $validator->extractWebsites($businessProfile);

            // Actualizar o crear el perfil
            if ($profile) {
                $profile->update($validData);
            } else {
                $profile = WhatsappBusinessProfile::create(array_merge($validData, [
                    'whatsapp_business_account_id' => $phone->whatsapp_business_account_id
                ]));
                $phone->whatsapp_business_profile_id = $profile->whatsapp_business_profile_id;
                $phone->save();
            }

            $this->syncWebsites($profile, $validatedWebsites);

        } catch (InvalidApiResponseException $e) {
            Log::channel('whatsapp')->error("Perfil inválido: {$e->getMessage()}");
        } catch (ApiException $e) {
            Log::channel('whatsapp')->error("Error API: {$e->getMessage()}");
        }
    }

    protected function syncWebsites(WhatsappBusinessProfile $profile, array $websites): void
    {
        // Eliminar websites existentes
        $profile->websites()->delete();

        // Crear nuevos registros
        foreach ($websites as $websiteData) {
            try {
                $profile->websites()->create([
                    'website' => $websiteData['url'] // Mapear 'url' al campo 'website' de la BD
                ]);
            } catch (\Exception $e) {
                Log::error("Error guardando website: {$e->getMessage()}", $websiteData);
            }
        }
    }
}