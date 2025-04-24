<?php

namespace ScriptDevelop\WhatsappManager\Services;

use Illuminate\Support\Facades\Log;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessProfile;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;
use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;

class AccountRegistrationService
{
    use GeneratesUlid; // Para generar ULIDs si es necesario

    public function __construct(
        protected WhatsappService $whatsappService
    ) {}

    /**
     * Registra o actualiza una cuenta empresarial de WhatsApp con todos sus datos asociados.
     * 
     * @param string $apiToken Token de acceso de Meta
     * @param string $whatsappBusinessId ID de la cuenta empresarial en WhatsApp
     * @return WhatsappBusinessAccount
     * @throws ApiException
     */
    public function registerFullAccount(string $apiToken, string $whatsappBusinessId): WhatsappBusinessAccount
    {
        // 1. Validación básica
        if (empty($apiToken)) {
            throw new \InvalidArgumentException('El token de API es requerido');
        }

        if (empty($whatsappBusinessId)) {
            throw new \InvalidArgumentException('El ID de la cuenta empresarial es requerido');
        }

        try {
            // 2. Obtener datos de la cuenta desde la API
            $businessAccountData = $this->whatsappService
                ->withTempToken($apiToken)
                ->getBusinessAccount($whatsappBusinessId);

            // 3. Registrar/Actualizar en base de datos
            $account = $this->upsertBusinessAccount($apiToken, $businessAccountData);

            // 4. Registrar números telefónicos asociados
            $this->registerPhoneNumbers($account);

            return $account->load('phoneNumbers');

        } catch (ApiException $e) {
            Log::error("Error API al registrar cuenta: " . $e->getMessage());
            throw new ApiException("Error en el registro: " . $e->getMessage(), $e->getCode(), $e->getDetails());
        }
    }

    /**
     * Registra o actualiza una cuenta empresarial.
     */
    public function register(array $data): WhatsappBusinessAccount
    {
        if (empty($data['api_token'])) {
            throw new \InvalidArgumentException('El token de API es requerido');
        }

        if (empty($data['business_id'])) {
            throw new \InvalidArgumentException('El ID de la cuenta es requerido');
        }

        try {
            $accountData = $this->whatsappService
                ->withTempToken($data['api_token'])
                ->getBusinessAccount($data['business_id']);

            $account = $this->upsertBusinessAccount($data['api_token'], $accountData);
            $this->registerPhoneNumbers($account);

            return $account->load('phoneNumbers');

        } catch (ApiException $e) {
            Log::error("Error al registrar cuenta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crea o actualiza la cuenta empresarial en la base de datos
     */
    protected function upsertBusinessAccount(string $apiToken, array $apiData): WhatsappBusinessAccount
    {
        return WhatsappBusinessAccount::updateOrCreate(
            ['whatsapp_business_id' => $apiData['id']],
            [
                'name' => $apiData['name'] ?? 'Cuenta sin nombre',
                'api_token' => $apiToken,
                'phone_number_id' => $apiData['id'], // Ajustar según respuesta real
                'message_template_namespace' => $apiData['message_template_namespace'] ?? null
            ]
        );
    }

    /**
     * Registra todos los números telefónicos asociados a la cuenta
     */
    protected function registerPhoneNumbers(WhatsappBusinessAccount $account): void
    {
        try {
            $phoneNumbers = $this->whatsappService
                ->forAccount($account->whatsapp_business_id)
                ->getPhoneNumbers($account->whatsapp_business_id);

            foreach ($phoneNumbers as $phoneData) {
                $this->registerSinglePhoneNumber($account, $phoneData);
            }

        } catch (ApiException $e) {
            Log::error("Error registrando números: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registra un número telefónico y su perfil asociado
     */
    protected function registerSinglePhoneNumber(WhatsappBusinessAccount $account, array $phoneData): void
    {
        // Registrar número
        $phone = WhatsappPhoneNumber::updateOrCreate(
            ['phone_number_id' => $phoneData['id']],
            [
                'whatsapp_business_account_id' => $account->whatsapp_business_id,
                'display_phone_number' => $phoneData['display_phone_number'],
                'verified_name' => $phoneData['verified_name']
            ]
        );

        // Registrar perfil empresarial del número
        $this->registerBusinessProfile($phone);
    }

    /**
     * Registra el perfil empresarial de un número telefónico
     */
    protected function registerBusinessProfile(WhatsappPhoneNumber $phone): void
    {
        try {
            $profileData = $this->whatsappService
                ->forAccount($phone->whatsapp_business_account_id)
                ->getBusinessProfile(
                    $phone->phone_number_id,
                    ['about', 'address', 'description', 'email', 'profile_picture_url', 'websites', 'vertical']
                );

            WhatsappBusinessProfile::updateOrCreate(
                ['whatsapp_phone_id' => $phone->whatsapp_phone_id],
                $this->mapProfileData($profileData)
            );

        } catch (ApiException $e) {
            Log::error("Perfil no registrado para {$phone->phone_number_id}: " . $e->getMessage());
        }
    }

    /**
     * Mapea los datos de la API al formato de la base de datos
     */
    protected function mapProfileData(array $apiData): array
    {
        return [
            'about' => $apiData['about'] ?? null,
            'address' => $apiData['address'] ?? null,
            'description' => $apiData['description'] ?? null,
            'email' => $apiData['email'] ?? null,
            'profile_picture_url' => $apiData['profile_picture_url']['url'] ?? null, // Ejemplo de anidación
            'vertical' => $apiData['vertical'] ?? 'OTHER',
            'websites' => $this->extractWebsites($apiData)
        ];
    }

    /**
     * Extrae URLs de sitios web del formato de la API
     */
    protected function extractWebsites(array $apiData): ?array
    {
        if (!isset($apiData['websites'])) return null;

        return array_map(fn($website) => [
            'url' => $website['url'],
            'type' => $website['type'] ?? 'WEB'
        ], $apiData['websites']);
    }
}