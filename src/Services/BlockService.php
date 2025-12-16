<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\WhatsappApi\Endpoints;
use ScriptDevelop\WhatsappManager\WhatsappApi\Exceptions\ApiException;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;
use ScriptDevelop\WhatsappManager\Models\WhatsappPhoneNumber;
use ScriptDevelop\WhatsappManager\Models\BlockedUser;
use ScriptDevelop\WhatsappManager\Models\Contact;
use Illuminate\Support\Facades\Log;

class BlockService
{
    protected $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function blockUsers(string $phoneNumberId, array $users): array
    {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) throw new \RuntimeException(whatsapp_trans('messages.phone_number_not_found'));
        
        // Formatear números
        $formattedUsers = $this->formatUsers($phone, $users);
        
        // Filtrar usuarios ya bloqueados
        $alreadyBlocked = $this->getBlockedStatuses($phone, $formattedUsers)
            ->whereNull('unblocked_at')
            ->pluck('user_wa_id')
            ->toArray();
        
        $usersToBlock = array_diff($formattedUsers, $alreadyBlocked);
        
        if (empty($usersToBlock)) {
            return [
                'success' => true,
                'message' => whatsapp_trans('messages.users_already_blocked'),
                'already_blocked' => $alreadyBlocked
            ];
        }
        
        $endpoint = Endpoints::build(
            Endpoints::BLOCK_USERS,
            ['phone_number_id' => $phone->api_phone_number_id]
        );
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'block_users' => array_map(fn($user) => ['user' => $user], $usersToBlock)
        ];
        
        $response = $this->apiClient->request(
            'POST',
            $endpoint,
            [],
            $payload,
            [],
            $this->getAuthHeaders($phone->businessAccount)
        );
        
        // Persistir solo si la operación fue exitosa
        if (isset($response['success']) && $response['success']) {
            $this->persistBlockedUsers($phone, $usersToBlock);
        }
        
        return $response;
    }

    public function unblockUsers(string $phoneNumberId, array $users): array
    {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) throw new \RuntimeException(whatsapp_trans('messages.phone_number_not_found'));
        
        // Formatear números
        $formattedUsers = $this->formatUsers($phone, $users);
        
        // Filtrar usuarios no bloqueados
        $notBlocked = $this->getBlockedStatuses($phone, $formattedUsers)
            ->whereNotNull('unblocked_at')
            ->orWhereNull('blocked_at')
            ->pluck('user_wa_id')
            ->toArray();
        
        $usersToUnblock = array_diff($formattedUsers, $notBlocked);
        
        if (empty($usersToUnblock)) {
            return [
                'success' => true,
                'message' => whatsapp_trans('messages.users_already_unblocked'),
                'already_unblocked' => $notBlocked
            ];
        }

        $endpoint = Endpoints::build(
            Endpoints::UNBLOCK_USERS,
            ['phone_number_id' => $phone->api_phone_number_id]
        );
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'block_users' => array_map(fn($user) => ['user' => $user], $usersToUnblock)
        ];
        
        try {
            $response = $this->apiClient->request(
                'DELETE', 
                $endpoint,
                [],
                $payload,
                [],
                $this->getAuthHeaders($phone->businessAccount)
            );
            
            // Persistir desbloqueo si fue exitoso
            if (isset($response['success']) && $response['success']) {
                $this->persistUnblockedUsers($phone, $usersToUnblock);
            }
            
            return $response;
        } catch (ApiException $e) {
            if ($e->getCode() === 400 || $e->getCode() === 405) {
                $response = $this->apiClient->request(
                    'POST', 
                    $endpoint,
                    [],
                    $payload,
                    ['_method' => 'DELETE'],
                    $this->getAuthHeaders($phone->businessAccount)
                );
                
                // Persistir desbloqueo si fue exitoso
                if (isset($response['success']) && $response['success']) {
                    $this->persistUnblockedUsers($phone, $usersToUnblock);
                }
                
                return $response;
            }
            throw $e;
        }
    }

    protected function getBlockedStatuses(WhatsappPhoneNumber $phone, array $users)
    {
        return BlockedUser::where('phone_number_id', $phone->phone_number_id)
            ->whereIn('user_wa_id', $users);
    }

    protected function formatUsers(WhatsappPhoneNumber $phone, array $users): array
    {
        return array_map(function($user) use ($phone) {
            // Eliminar espacios y caracteres no numéricos
            $cleanNumber = preg_replace('/[^0-9]/', '', $user);
            
            // Asegurar formato internacional
            if (!str_starts_with($cleanNumber, $phone->country_code)) {
                $cleanNumber = $phone->country_code . ltrim($cleanNumber, '0');
            }
            
            return $cleanNumber;
        }, $users);
    }

    protected function findOrCreateContact(string $businessAccountId, string $userIdentifier): Contact
    {
        $contact = Contact::where('wa_id', $userIdentifier)
                    ->orWhere('phone_number', $userIdentifier)
                    ->first();

        if (!$contact) {
            $contact = Contact::create([
                'wa_id' => $userIdentifier,
                'phone_number' => $userIdentifier,
                'first_name' => whatsapp_trans('messages.blocked_user'),
                'accepts_marketing' => false,
                'marketing_opt_out_at' => now()
            ]);
        }

        return $contact;
    }

    protected function persistBlockedUsers(WhatsappPhoneNumber $phone, array $users): void
    {
        foreach ($users as $user) {
            $contact = $this->findOrCreateContact($phone->whatsapp_business_account_id, $user);
            
            BlockedUser::updateOrCreate(
                [
                    'phone_number_id' => $phone->phone_number_id,
                    'user_wa_id' => $user
                ],
                [
                    'contact_id' => $contact->contact_id,
                    'blocked_at' => now(),
                    'unblocked_at' => null
                ]
            );
        }
    }

    protected function persistUnblockedUsers(WhatsappPhoneNumber $phone, array $users): void
    {
        BlockedUser::where('phone_number_id', $phone->phone_number_id)
            ->whereIn('user_wa_id', $users)
            ->update(['unblocked_at' => now()]);
    }

    public function listBlockedUsers(
        string $phoneNumberId,
        int $limit = 50,
        ?string $after = null,
        ?string $before = null
    ): array {
        $phone = WhatsappModelResolver::phone_number()->find($phoneNumberId);
        if (!$phone) {
            throw new \RuntimeException(whatsapp_trans('messages.phone_number_not_found'));
        }

        $endpoint = Endpoints::build(
            Endpoints::LIST_BLOCKED_USERS,
            ['phone_number_id' => $phone->api_phone_number_id]
        );
        
        $queryParams = ['limit' => $limit];
        
        if ($after) {
            $queryParams['after'] = $after;
        }
        
        if ($before) {
            $queryParams['before'] = $before;
        }
        
        return $this->apiClient->request(
            'GET', 
            $endpoint,
            [], 
            null,
            $queryParams,
            $this->getAuthHeaders($phone->businessAccount)
        );
    }

    protected function getAuthHeaders($businessAccount): array
    {
        return [
            'Authorization' => 'Bearer ' . $businessAccount->api_token
        ];
    }
}